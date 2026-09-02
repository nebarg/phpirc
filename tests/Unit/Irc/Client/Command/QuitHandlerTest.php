<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Command\QuitHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class QuitHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>, string}> */
    public static function reasons(): iterable
    {
        yield 'supplied reason' => [['Gone for lunch'], 'Quit: Gone for lunch'];
        yield 'missing reason' => [[], 'Quit: '];
        yield 'empty reason' => [[''], 'Quit: '];
    }

    #[Test]
    public function it_handles_quit_before_or_after_registration(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('QUIT', $handler->command());
        $this->assertInstanceOf(PreRegistrationCommandHandler::class, $handler);
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('reasons')]
    public function it_notifies_shared_clients_and_closes_the_connection(
        array $parameters,
        string $reason,
    ): void {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'QUIT', parameters: $parameters),
        );

        $this->assertCount(1, $johnConnection->messages);
        $this->assertSame('irc.test', $johnConnection->messages[0]->source);
        $this->assertSame('ERROR', $johnConnection->messages[0]->command);
        $this->assertSame(
            ["Closing Link: John ({$reason})"],
            $johnConnection->messages[0]->parameters,
        );
        $this->assertSame(1, $johnConnection->closeCalls);
        $this->assertCount(1, $janeConnection->messages);
        $this->assertSame('John', $janeConnection->messages[0]->source);
        $this->assertSame('QUIT', $janeConnection->messages[0]->command);
        $this->assertSame([$reason], $janeConnection->messages[0]->parameters);
        $this->assertNull($clients->connectionFor($john));
        $this->assertFalse($channels->find('#php')?->has($john));
    }

    #[Test]
    public function it_closes_an_unregistered_client_without_notifying_anyone(): void
    {
        [$handler, $clients] = $this->handler();
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'QUIT'),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame(
            ['Closing Link: * (Quit: )'],
            $connection->messages[0]->parameters,
        );
        $this->assertSame(1, $connection->closeCalls);
        $this->assertNull($clients->connectionFor($client));
    }

    /** @return array{QuitHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new QuitHandler(
                departure: new ClientDeparture(
                    clients: $clients,
                    channels: $channels,
                    broadcaster: new ChannelBroadcaster($clients, $channels),
                ),
                serverName: new ServerName('irc.test'),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(string $nickname, ClientRegistry $clients): array
    {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);

        return [$client, $connection];
    }
}
