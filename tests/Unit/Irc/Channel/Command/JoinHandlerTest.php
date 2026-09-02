<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelNamesResponseFactory;
use PhpIrc\Irc\Channel\ChannelNameValidator;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Command\JoinHandler;
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

final class JoinHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingChannels(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_join_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('JOIN', $handler->command());
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
            new Message(command: 'JOIN', parameters: $parameters),
        );

        $this->assertResponse(
            $connection,
            '461',
            ['John', 'JOIN', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_rejects_an_invalid_channel_name(): void
    {
        [$handler, $channels] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'JOIN', parameters: ['not-a-channel']),
        );

        $this->assertNull($channels->find('not-a-channel'));
        $this->assertResponse(
            $connection,
            '403',
            ['John', 'not-a-channel', 'No such channel'],
        );
    }

    #[Test]
    public function it_creates_and_joins_a_channel(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'JOIN', parameters: ['#php']),
        );

        $channel = $channels->find('#php');
        $this->assertNotNull($channel);
        $this->assertSame($client, $channel->memberships()[0]->client);
        $this->assertTrue($channel->memberships()[0]->isOperator);
        $this->assertCount(3, $connection->messages);
        $this->assertSame('John', $connection->messages[0]->source);
        $this->assertSame('JOIN', $connection->messages[0]->command);
        $this->assertSame(['#php'], $connection->messages[0]->parameters);
        $this->assertResponse(
            $connection,
            '353',
            ['John', '=', '#php', '@John'],
            index: 1,
        );
        $this->assertResponse(
            $connection,
            '366',
            ['John', '#php', 'End of /NAMES list'],
            index: 2,
        );
    }

    #[Test]
    public function it_broadcasts_a_join_and_sends_the_existing_names_to_the_new_member(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $channels->join('#PHP', $john);
        $clients->register($john, $johnConnection);
        $clients->register($jane, $janeConnection);

        $handler->handle(
            new CommandContext($janeConnection, $jane),
            new Message(command: 'JOIN', parameters: ['#php']),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertSame('Jane', $johnConnection->messages[0]->source);
        $this->assertSame('JOIN', $johnConnection->messages[0]->command);
        $this->assertSame(['#PHP'], $johnConnection->messages[0]->parameters);
        $this->assertCount(3, $janeConnection->messages);
        $this->assertSame($johnConnection->messages[0], $janeConnection->messages[0]);
        $this->assertResponse(
            $janeConnection,
            '353',
            ['Jane', '=', '#PHP', '@John Jane'],
            index: 1,
        );
        $this->assertResponse(
            $janeConnection,
            '366',
            ['Jane', '#PHP', 'End of /NAMES list'],
            index: 2,
        );
    }

    #[Test]
    public function joining_a_channel_twice_is_silent(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');
        $channel = $channels->join('#php', $client);
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'JOIN', parameters: ['#PHP']),
        );

        $this->assertSame([], $connection->messages);
        $this->assertCount(1, $channel->memberships());
    }

    #[Test]
    public function it_processes_each_channel_in_a_comma_separated_list(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'JOIN', parameters: ['#one,invalid,#two']),
        );

        $this->assertTrue($channels->find('#one')?->has($client));
        $this->assertNull($channels->find('invalid'));
        $this->assertTrue($channels->find('#two')?->has($client));
        $this->assertCount(7, $connection->messages);
        $this->assertResponse(
            $connection,
            '403',
            ['John', 'invalid', 'No such channel'],
            index: 3,
        );
    }

    /** @return array{JoinHandler, ChannelRegistry, ClientRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $channels = new ChannelRegistry($caseMapper);
        $clients = new ClientRegistry($caseMapper);
        $responses = new NumericResponseFactory(new ServerName('irc.test'));

        return [
            new JoinHandler(
                channels: $channels,
                channelNames: new ChannelNameValidator(),
                broadcaster: new ChannelBroadcaster($clients, $channels),
                namesResponses: new ChannelNamesResponseFactory($responses),
                responses: $responses,
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
