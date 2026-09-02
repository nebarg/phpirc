<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Command\PartHandler;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class PartHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingChannels(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_part_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('PART', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingChannels')]
    public function it_rejects_a_missing_channel(array $parameters): void
    {
        [$handler] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'PART', parameters: $parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            $connection,
            '461',
            ['John', 'PART', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_rejects_a_channel_that_does_not_exist(): void
    {
        [$handler] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'PART', parameters: ['#missing']),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            $connection,
            '403',
            ['John', '#missing', 'No such channel'],
        );
    }

    #[Test]
    public function it_rejects_a_channel_the_client_has_not_joined(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $channel = $channels->join('#php', $john);
        $clients->register($john, $johnConnection);

        $handler->handle(
            new CommandContext($janeConnection, $jane),
            new Message(command: 'PART', parameters: ['#PHP']),
        );

        $this->assertCount(1, $janeConnection->messages);
        $this->assertResponse(
            $janeConnection,
            '442',
            ['Jane', '#php', "You're not on that channel"],
        );
        $this->assertSame([], $johnConnection->messages);
        $this->assertTrue($channel->has($john));
        $this->assertSame($channel, $channels->find('#php'));
    }

    #[Test]
    public function it_broadcasts_before_removing_the_client_from_the_channel(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $channel = $channels->join('#PHP', $john);
        $channels->join('#php', $jane);
        $clients->register($john, $johnConnection);
        $clients->register($jane, $janeConnection);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PART', parameters: ['#php']),
        );

        $this->assertPart($johnConnection, ['#PHP']);
        $this->assertPart($janeConnection, ['#PHP']);
        $this->assertSame($johnConnection->messages[0], $janeConnection->messages[0]);
        $this->assertFalse($channel->has($john));
        $this->assertTrue($channel->has($jane));
        $this->assertSame($channel, $channels->find('#php'));
    }

    #[Test]
    public function it_includes_the_part_reason_in_the_broadcast(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);
        $clients->register($john, $johnConnection);
        $clients->register($jane, $janeConnection);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PART', parameters: ['#php', 'Gone for lunch']),
        );

        $this->assertPart($johnConnection, ['#php', 'Gone for lunch']);
        $this->assertPart($janeConnection, ['#php', 'Gone for lunch']);
    }

    #[Test]
    public function it_removes_the_channel_when_its_final_member_parts(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');
        $channels->join('#php', $client);
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'PART', parameters: ['#php', 'Goodbye']),
        );

        $this->assertPart($connection, ['#php', 'Goodbye']);
        $this->assertNull($channels->find('#php'));
    }

    #[Test]
    public function it_processes_each_channel_in_a_comma_separated_list(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $first = $channels->join('#one', $john);
        $channels->join('#one', $jane);
        $second = $channels->join('#two', $john);
        $clients->register($john, $johnConnection);
        $clients->register($jane, $janeConnection);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'PART', parameters: ['#ONE,#missing,#two', 'Done']),
        );

        $this->assertCount(3, $johnConnection->messages);
        $this->assertPart($johnConnection, ['#one', 'Done'], index: 0);
        $this->assertResponse(
            $johnConnection,
            '403',
            ['John', '#missing', 'No such channel'],
            index: 1,
        );
        $this->assertPart($johnConnection, ['#two', 'Done'], index: 2);
        $this->assertPart($janeConnection, ['#one', 'Done']);
        $this->assertFalse($first->has($john));
        $this->assertTrue($first->has($jane));
        $this->assertFalse($second->has($john));
        $this->assertSame($first, $channels->find('#one'));
        $this->assertNull($channels->find('#two'));
    }

    /** @return array{PartHandler, ChannelRegistry, ClientRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $channels = new ChannelRegistry($caseMapper);
        $clients = new ClientRegistry($caseMapper);

        return [
            new PartHandler(
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients),
                responses: new NumericResponseFactory(new ServerName('irc.test')),
            ),
            $channels,
            $clients,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(string $nickname): array
    {
        $client = new Client();
        $client->setNickname($nickname);

        return [$client, new RecordingConnection()];
    }

    /** @param list<string> $parameters */
    private function assertPart(
        RecordingConnection $connection,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame([], $connection->messages[$index]->tags);
        $this->assertSame('John', $connection->messages[$index]->source);
        $this->assertSame('PART', $connection->messages[$index]->command);
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
