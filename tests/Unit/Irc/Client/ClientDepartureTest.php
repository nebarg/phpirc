<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ClientDepartureTest extends TestCase
{
    #[Test]
    public function it_notifies_each_shared_client_once_and_removes_client_state(): void
    {
        [$departure, $clients, $channels] = $this->departure();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        [$other, $otherConnection] = $this->connectedClient('Other', $clients);
        [, $outsiderConnection] = $this->connectedClient('Outside', $clients);
        $first = $channels->join('#one', $john);
        $channels->join('#one', $jane);
        $second = $channels->join('#two', $john);
        $channels->join('#two', $jane);
        $channels->join('#two', $other);

        $departure->depart($john, 'Quit: Gone for lunch');

        $this->assertSame([], $johnConnection->messages);
        $this->assertQuit($janeConnection, 'Quit: Gone for lunch');
        $this->assertQuit($otherConnection, 'Quit: Gone for lunch');
        $this->assertSame([], $outsiderConnection->messages);
        $this->assertFalse($first->has($john));
        $this->assertFalse($second->has($john));
        $this->assertSame($first, $channels->find('#one'));
        $this->assertSame($second, $channels->find('#two'));
        $this->assertNull($clients->findByNickname('John'));
        $this->assertNull($clients->connectionFor($john));
    }

    #[Test]
    public function it_ignores_disconnected_shared_clients(): void
    {
        [$departure, $clients, $channels] = $this->departure();
        [$john] = $this->connectedClient('John', $clients);
        $disconnected = new Client();
        $disconnected->setNickname('Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $disconnected);

        $departure->depart($john, 'Connection closed');

        $this->assertTrue($channels->find('#php')?->has($disconnected));
        $this->assertNull($clients->connectionFor($john));
    }

    #[Test]
    public function it_is_safe_to_process_the_same_departure_twice(): void
    {
        [$departure, $clients, $channels] = $this->departure();
        [$john] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $departure->depart($john, 'Quit: Goodbye');
        $departure->depart($john, 'Connection closed');

        $this->assertCount(1, $janeConnection->messages);
        $this->assertQuit($janeConnection, 'Quit: Goodbye');
    }

    #[Test]
    public function it_cleans_up_state_when_notifying_a_shared_client_fails(): void
    {
        [$departure, $clients, $channels] = $this->departure();
        [$john] = $this->connectedClient('John', $clients);
        $jane = new Client();
        $clients->register($jane, new class implements Connection {
            public function send(Message $message): void
            {
                throw new RuntimeException('Sending failed.');
            }

            public function close(string $reason = 'Connection closed'): void {}

            public function pongReceived(string $token): void {}
        });
        $clients->claimNickname($jane, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        try {
            $departure->depart($john, 'Connection closed');
            $this->fail('Expected the sending exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sending failed.', $exception->getMessage());
            $this->assertNull($clients->connectionFor($john));
            $this->assertNull($clients->findByNickname('John'));
            $this->assertFalse($channels->find('#php')?->has($john));
        }
    }

    /** @return array{ClientDeparture, ClientRegistry, ChannelRegistry} */
    private function departure(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new ClientDeparture(
                clients: $clients,
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients, $channels),
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

    private function assertQuit(RecordingConnection $connection, string $reason): void
    {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('John', $connection->messages[0]->source);
        $this->assertSame('QUIT', $connection->messages[0]->command);
        $this->assertSame([$reason], $connection->messages[0]->parameters);
    }
}
