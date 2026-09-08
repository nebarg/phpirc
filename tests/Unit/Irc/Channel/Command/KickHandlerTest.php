<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Command\KickHandler;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Response\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class KickHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingParameters(): iterable
    {
        yield 'no parameters' => [[]];
        yield 'channel only' => [['#php']];
        yield 'empty channel' => [['', 'Jane']];
        yield 'empty nickname' => [['#php', '']];
    }

    #[Test]
    public function it_handles_the_kick_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('KICK', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingParameters')]
    public function it_rejects_missing_parameters(array $parameters): void
    {
        [$handler, , $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'KICK', parameters: $parameters),
        );

        $this->assertResponse($connection, '461', ['John', 'KICK', 'Not enough parameters']);
    }

    #[Test]
    public function it_rejects_an_unknown_channel(): void
    {
        [$handler, , $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'KICK', parameters: ['#missing', 'Jane']),
        );

        $this->assertResponse($connection, '403', ['John', '#missing', 'No such channel']);
    }

    #[Test]
    public function it_rejects_a_requester_outside_the_channel(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channel = $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'KICK', parameters: ['#PHP', 'Jane']),
        );

        $this->assertResponse($johnConnection, '442', ['John', '#php', "You're not on that channel"]);
        $this->assertSame([], $janeConnection->messages);
        $this->assertTrue($channel->hasMember($jane));
    }

    #[Test]
    public function it_rejects_a_non_operator_member(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$operator] = $this->register($clients, 'Jane');
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$target, $targetConnection] = $this->register($clients, 'Fred');
        $channel = $channels->join('#php', $operator);
        $channels->join('#php', $john);
        $channels->join('#php', $target);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'Fred']),
        );

        $this->assertResponse($johnConnection, '482', ['John', '#php', "You're not channel operator"]);
        $this->assertSame([], $targetConnection->messages);
        $this->assertTrue($channel->hasMember($target));
    }

    #[Test]
    public function it_rejects_an_unknown_target_as_not_in_the_channel(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'Missing']),
        );

        $this->assertResponse(
            $connection,
            '441',
            ['John', 'Missing', '#php', "They aren't on that channel"],
        );
    }

    #[Test]
    public function it_rejects_a_known_client_outside_the_channel_using_their_canonical_nickname(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $this->register($clients, 'Jane');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'jAnE']),
        );

        $this->assertResponse(
            $connection,
            '441',
            ['John', 'Jane', '#php', "They aren't on that channel"],
        );
    }

    #[Test]
    public function it_broadcasts_the_kick_before_removing_the_target(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        [$fred, $fredConnection] = $this->register($clients, 'Fred');
        $channel = $channels->join('#php', $john);
        $channels->join('#php', $jane);
        $channels->join('#php', $fred);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'KICK', parameters: ['#PHP', 'jAnE', 'Breaking the rules']),
        );

        $this->assertKick($johnConnection, ['#php', 'Jane', 'Breaking the rules']);
        $this->assertKick($janeConnection, ['#php', 'Jane', 'Breaking the rules']);
        $this->assertKick($fredConnection, ['#php', 'Jane', 'Breaking the rules']);
        $this->assertSame($johnConnection->messages[0], $janeConnection->messages[0]);
        $this->assertSame($johnConnection->messages[0], $fredConnection->messages[0]);
        $this->assertFalse($channel->hasMember($jane));
        $this->assertTrue($channel->hasMember($john));
        $this->assertTrue($channel->hasMember($fred));
        $this->assertSame($channel, $channels->find('#PHP'));
    }

    #[Test]
    public function it_uses_the_requesters_nickname_as_the_default_reason(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane, $janeConnection] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'Jane']),
        );

        $this->assertKick($johnConnection, ['#php', 'Jane', 'John']);
        $this->assertKick($janeConnection, ['#php', 'Jane', 'John']);
    }

    #[Test]
    public function it_preserves_an_explicitly_empty_reason(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->register($clients, 'John');
        [$jane] = $this->register($clients, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'Jane', '']),
        );

        $this->assertKick($johnConnection, ['#php', 'Jane', '']);
    }

    #[Test]
    public function it_removes_the_channel_when_its_final_member_kicks_themselves(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'KICK', parameters: ['#php', 'John']),
        );

        $this->assertKick($connection, ['#php', 'John', 'John']);
        $this->assertNull($channels->find('#php'));
    }

    #[Test]
    public function it_limits_the_reason_to_the_outbound_message_size(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        $nicknameLength = ServerLimits::MAX_NICKNAME_BYTES;
        [$john, $johnConnection] = $this->register($clients, str_repeat('j', $nicknameLength));
        [$jane] = $this->register($clients, str_repeat('a', $nicknameLength));
        $channels->join('#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1), $john);
        $channels->join('#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1), $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(
                command: 'KICK',
                parameters: [
                    '#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1),
                    str_repeat('a', $nicknameLength),
                    str_repeat('Reason ', 100),
                ],
            ),
        );

        $message = $johnConnection->messages[0];
        $this->assertTrue(new MessageSize(new MessageEncoder())->fits($message));
        $this->assertLessThan(strlen(str_repeat('Reason ', 100)), strlen($message->parameter(2)));
    }

    /** @return array{KickHandler, ChannelRegistry, ClientRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $channels = new ChannelRegistry($caseMapper);
        $clients = new ClientRegistry($caseMapper);
        $errors = new NumericErrorResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
            new ByteStringTruncator(),
        );

        return [
            new KickHandler(
                channels: $channels,
                clients: $clients,
                broadcaster: new ChannelBroadcaster($clients, $channels),
                channelAccess: new ChannelAccessPolicy(),
                permissionResponses: new ChannelPermissionResponseFactory($errors),
                errors: $errors,
                messageText: new MessageTextLimiter(
                    new MessageSize(new MessageEncoder()),
                    new ByteStringTruncator(),
                ),
            ),
            $channels,
            $clients,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function register(ClientRegistry $clients, string $nickname): array
    {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();

        return [$client, $connection];
    }

    /** @param list<string> $parameters */
    private function assertKick(RecordingConnection $connection, array $parameters): void
    {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('John', $connection->messages[0]->source);
        $this->assertSame('KICK', $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
