<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Mode;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelModeResponseFactory;
use PhpIrc\Irc\Channel\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Mode\ChannelModeHandler;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ChannelModeHandlerTest extends TestCase
{
    /** @return iterable<string, array{MembershipMode, string, bool}> */
    public static function membershipModeChanges(): iterable
    {
        yield 'grant operator' => [MembershipMode::Operator, '+o', false];
        yield 'remove operator' => [MembershipMode::Operator, '-o', true];
        yield 'grant voice' => [MembershipMode::Voice, '+v', false];
        yield 'remove voice' => [MembershipMode::Voice, '-v', true];
    }

    /** @return iterable<string, array{ChannelMode, string, bool}> */
    public static function channelModeChanges(): iterable
    {
        yield 'enable moderated' => [ChannelMode::Moderated, '+m', true];
        yield 'disable no external messages' => [ChannelMode::NoExternalMessages, '-n', false];
        yield 'disable protected topic' => [ChannelMode::ProtectedTopic, '-t', false];
    }

    #[Test]
    public function it_rejects_an_unknown_channel(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#missing']),
        );

        $this->assertResponse($connection, '403', ['John', '#missing', 'No such channel']);
    }

    #[Test]
    public function it_returns_the_current_channel_modes_and_creation_time(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channel = $channels->join('#PHP', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse($connection, '324', ['John', '#PHP', '+nt']);
        $this->assertResponse(
            connection: $connection,
            command: '329',
            parameters: ['John', '#PHP', (string) $channel->createdAt->getTimestamp()],
            index: 1,
        );
    }

    #[Test]
    public function it_treats_an_empty_modestring_as_a_query(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php', '']),
        );

        $this->assertSame(['324', '329'], array_column($connection->messages, 'command'));
    }

    #[Test]
    public function it_rejects_a_change_from_a_client_outside_the_channel(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        [$jane] = $this->register($clients, 'Jane');
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+o', 'John']),
        );

        $this->assertResponse($connection, '482', ['John', '#php', "You're not channel operator"]);
    }

    #[Test]
    public function it_rejects_a_change_from_a_non_operator_member(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john] = $this->register($clients, 'John');
        [$jane, $connection] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($connection, $jane),
            new Message(command: 'MODE', parameters: ['#php', '+v', 'Jane']),
        );

        $this->assertResponse($connection, '482', ['Jane', '#php', "You're not channel operator"]);
    }

    #[Test]
    #[DataProvider('membershipModeChanges')]
    public function an_operator_can_change_membership_modes(
        MembershipMode $mode,
        string $modeString,
        bool $initiallyGranted,
    ): void {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channel = $channels->join('#php', $john);
        $membership = $channels->join('#php', $jane)->membershipFor($jane);
        $this->assertNotNull($membership);

        if ($initiallyGranted) {
            $membership->grant($mode);
        }

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#PHP', $modeString, 'jAnE']),
        );

        $this->assertSame(! $initiallyGranted, $membership->has($mode));
        $this->assertCount(1, $johnConnection->messages);
        $this->assertCount(1, $janeConnection->messages);
        $this->assertSame($johnConnection->messages[0], $janeConnection->messages[0]);
        $this->assertSame('John', $johnConnection->messages[0]->source);
        $this->assertSame('MODE', $johnConnection->messages[0]->command);
        $this->assertSame([$channel->name, $modeString, 'Jane'], $johnConnection->messages[0]->parameters);
    }

    #[Test]
    #[DataProvider('channelModeChanges')]
    public function an_operator_can_change_channel_modes(
        ChannelMode $mode,
        string $modeString,
        bool $enabledAfter,
    ): void {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channel = $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#PHP', $modeString]),
        );

        $this->assertSame($enabledAfter, $channel->hasMode($mode));
        $this->assertCount(1, $johnConnection->messages);
        $this->assertSame($johnConnection->messages, $janeConnection->messages);
        $this->assertSame('John', $johnConnection->messages[0]->source);
        $this->assertSame('MODE', $johnConnection->messages[0]->command);
        $this->assertSame(['#php', $modeString], $johnConnection->messages[0]->parameters);
    }

    #[Test]
    public function it_applies_and_broadcasts_mixed_channel_and_membership_mode_changes(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane] = $this->register($clients, 'Jane');
        [$fred] = $this->register($clients, 'Fred');
        $channel = $channels->join('#php', $john);
        $janeMembership = $channels->join('#php', $jane)->membershipFor($jane);
        $fredMembership = $channels->join('#php', $fred)->membershipFor($fred);
        $this->assertNotNull($janeMembership);
        $this->assertNotNull($fredMembership);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+mov-n', 'Jane', 'Fred']),
        );

        $this->assertTrue($channel->hasMode(ChannelMode::Moderated));
        $this->assertFalse($channel->hasMode(ChannelMode::NoExternalMessages));
        $this->assertTrue($janeMembership->has(MembershipMode::Operator));
        $this->assertTrue($fredMembership->has(MembershipMode::Voice));
        $this->assertSame(
            ['#php', '+mov-n', 'Jane', 'Fred'],
            $johnConnection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_applies_and_broadcasts_combined_membership_mode_changes(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane] = $this->register($clients, 'Jane');
        [$fred] = $this->register($clients, 'Fred');
        [$mary] = $this->register($clients, 'Mary');
        $channel = $channels->join('#php', $john);
        $janeMembership = $channels->join('#php', $jane)->membershipFor($jane);
        $fredMembership = $channels->join('#php', $fred)->membershipFor($fred);
        $maryMembership = $channels->join('#php', $mary)->membershipFor($mary);
        $this->assertNotNull($janeMembership);
        $this->assertNotNull($fredMembership);
        $this->assertNotNull($maryMembership);
        $maryMembership->grant(MembershipMode::Voice);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(
                command: 'MODE',
                parameters: ['#php', '+ov-v', 'Jane', 'Fred', 'Mary'],
            ),
        );

        $this->assertTrue($janeMembership->has(MembershipMode::Operator));
        $this->assertTrue($fredMembership->has(MembershipMode::Voice));
        $this->assertFalse($maryMembership->has(MembershipMode::Voice));
        $this->assertSame(
            ['#php', '+ov-v', 'Jane', 'Fred', 'Mary'],
            $johnConnection->messages[0]->parameters,
        );
        $this->assertSame($channel, $channels->find('#php'));
    }

    #[Test]
    public function it_reports_unknown_modes_and_continues_applying_recognised_modes(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $membership = $channels->join('#php', $jane)->membershipFor($jane);
        $this->assertNotNull($membership);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+xv', 'Jane']),
        );

        $this->assertTrue($membership->has(MembershipMode::Voice));
        $this->assertCount(2, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '472',
            ['John', 'x', 'is unknown mode char to me'],
        );
        $this->assertSame(['#php', '+v', 'Jane'], $johnConnection->messages[1]->parameters);
        $this->assertSame($johnConnection->messages[1], $janeConnection->messages[0]);
    }

    #[Test]
    public function it_reports_an_unknown_target_nickname(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+o', 'Missing']),
        );

        $this->assertResponse($connection, '401', ['John', 'Missing', 'No such nick/channel']);
    }

    #[Test]
    public function it_reports_a_target_that_is_not_in_the_channel(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $this->register($clients, 'Jane');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+v', 'Jane']),
        );

        $this->assertResponse(
            $connection,
            '441',
            ['John', 'Jane', '#php', "They aren't on that channel"],
        );
    }

    #[Test]
    public function it_does_not_broadcast_changes_that_do_not_alter_membership_state(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+o-v', 'John', 'Jane']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertSame([], $janeConnection->messages);
    }

    #[Test]
    public function it_does_not_broadcast_channel_modes_that_do_not_change_state(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+nt-m']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertSame([], $janeConnection->messages);
    }

    #[Test]
    public function it_ignores_a_membership_mode_without_a_nickname_argument(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['#php', '+v']),
        );

        $this->assertSame([], $connection->messages);
    }

    /** @return array{ChannelModeHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $responses = new NumericResponseFactory(new ServerName('irc.test'));
        $errors = new NumericErrorResponseFactory($responses, new ByteStringTruncator());

        return [
            new ChannelModeHandler(
                channels: $channels,
                clients: $clients,
                broadcaster: new ChannelBroadcaster($clients, $channels),
                parser: new ModeChangeParser(),
                errors: $errors,
                modeResponses: new ChannelModeResponseFactory($responses),
                channelAccess: new ChannelAccessPolicy(),
                permissionResponses: new ChannelPermissionResponseFactory($errors),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function register(ClientRegistry $registry, string $nickname): array
    {
        $client = new Client();
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();
        $connection = new RecordingConnection();
        $registry->register($client, $connection);
        $registry->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame('irc.test', $connection->messages[$index]->source);
        $this->assertSame($command, $connection->messages[$index]->command);
        $this->assertSame($parameters, $connection->messages[$index]->parameters);
    }
}
