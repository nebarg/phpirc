<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Message\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Message\Command\PrivmsgHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Message\PrivmsgResponseFactory;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class PrivmsgHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingTargets(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    /** @return iterable<string, array{list<string>}> */
    public static function missingTexts(): iterable
    {
        yield 'missing parameter' => [['Jane']];
        yield 'empty parameter' => [['Jane', '']];
    }

    #[Test]
    public function it_handles_the_privmsg_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('PRIVMSG', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingTargets')]
    public function it_rejects_a_missing_target(array $parameters): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: $parameters),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '411',
            ['John', 'No recipient given (PRIVMSG)'],
        );
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingTexts')]
    public function it_rejects_missing_text(array $parameters): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: $parameters),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '412',
            ['John', 'No text to send'],
        );
    }

    #[Test]
    public function it_rejects_a_target_that_does_not_exist(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: ['Missing', 'Hello']),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '401',
            ['John', 'Missing', 'No such nick/channel'],
        );
    }

    #[Test]
    public function it_reports_when_a_target_channel_does_not_exist(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: ['#missing', 'Hello']),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '404',
            ['John', '#missing', 'Cannot send to channel'],
        );
    }

    #[Test]
    public function it_delivers_a_message_to_a_client_case_insensitively(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: ['jAnE', 'Hello Jane']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertMessage(
            $janeConnection,
            source: 'John',
            parameters: ['Jane', 'Hello Jane'],
        );
    }

    #[Test]
    public function it_delivers_a_message_to_the_sending_client_when_they_target_themselves(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: ['john', 'A note to myself']),
        );

        $this->assertMessage(
            $johnConnection,
            source: 'John',
            parameters: ['John', 'A note to myself'],
        );
    }

    #[Test]
    public function it_delivers_a_channel_message_to_every_member_except_the_sender(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channel = $channels->join('#PHP', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PRIVMSG', parameters: ['#php', 'Hello channel']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertMessage(
            $janeConnection,
            source: 'John',
            parameters: ['#PHP', 'Hello channel'],
        );
        $this->assertTrue($channel->hasMember($john));
        $this->assertTrue($channel->hasMember($jane));
    }

    #[Test]
    public function it_reports_when_channel_modes_prevent_delivery(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        [$outsider, $outsiderConnection] = $this->connectedClient('Outside', $clients);
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($outsiderConnection, $outsider),
            new Message(command: 'PRIVMSG', parameters: ['#PHP', 'Hello from outside']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertSame([], $janeConnection->messages);
        $this->assertResponse(
            $outsiderConnection,
            '404',
            ['Outside', '#php', 'Cannot send to channel'],
        );
    }

    #[Test]
    public function it_processes_each_target_in_a_comma_separated_list(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(
                command: 'PRIVMSG',
                parameters: ['#PHP,jAnE,Missing', 'Hello targets'],
            ),
        );

        $this->assertCount(2, $janeConnection->messages);
        $this->assertMessage(
            $janeConnection,
            source: 'John',
            parameters: ['#php', 'Hello targets'],
            index: 0,
        );
        $this->assertMessage(
            $janeConnection,
            source: 'John',
            parameters: ['Jane', 'Hello targets'],
            index: 1,
        );
        $this->assertCount(1, $johnConnection->messages);
        $this->assertResponse(
            $johnConnection,
            '401',
            ['John', 'Missing', 'No such nick/channel'],
        );
    }

    /** @return array{PrivmsgHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new PrivmsgHandler(
                delivery: new MessageDelivery(
                    clients: $clients,
                    channels: $channels,
                    broadcaster: new ChannelBroadcaster($clients, $channels),
                    targets: new TargetClassifier(new ChannelTypes()),
                    channelAccess: new ChannelAccessPolicy(),
                ),
                privmsgResponses: new PrivmsgResponseFactory(
                    new NumericErrorResponseFactory(
                        new NumericResponseFactory(new ServerName('irc.test')),
                    ),
                ),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(
        string $nickname,
        ClientRegistry $clients,
    ): array {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    /** @param list<string> $parameters */
    private function assertMessage(
        RecordingConnection $connection,
        string $source,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame([], $connection->messages[$index]->tags);
        $this->assertSame($source, $connection->messages[$index]->source);
        $this->assertSame('PRIVMSG', $connection->messages[$index]->command);
        $this->assertSame($parameters, $connection->messages[$index]->parameters);
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame([], $connection->messages[$index]->tags);
        $this->assertSame('irc.test', $connection->messages[$index]->source);
        $this->assertSame($command, $connection->messages[$index]->command);
        $this->assertSame($parameters, $connection->messages[$index]->parameters);
    }
}
