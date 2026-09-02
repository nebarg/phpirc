<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ClientConnectionLifecycleTest extends TestCase
{
    #[Test]
    public function it_registers_a_connected_client(): void
    {
        [$lifecycle, $clients] = $this->lifecycle();
        $client = new Client();
        $connection = new RecordingConnection();

        $lifecycle->connected($client, $connection);

        $this->assertSame($connection, $clients->connectionFor($client));
    }

    #[Test]
    public function it_releases_all_client_state_when_disconnected(): void
    {
        [$lifecycle, $clients, $channels] = $this->lifecycle();
        $client = new Client();
        $connection = new RecordingConnection();
        $lifecycle->connected($client, $connection);
        $clients->claimNickname($client, 'John');
        $channels->join('#php', $client);

        $lifecycle->disconnected($client);

        $this->assertNull($clients->findByNickname('John'));
        $this->assertNull($clients->connectionFor($client));
        $this->assertNull($channels->find('#php'));
    }

    #[Test]
    public function it_notifies_shared_clients_when_a_connection_ends(): void
    {
        [$lifecycle, $clients, $channels] = $this->lifecycle();
        $john = new Client();
        $jane = new Client();
        $johnConnection = new RecordingConnection();
        $janeConnection = new RecordingConnection();
        $lifecycle->connected($john, $johnConnection);
        $lifecycle->connected($jane, $janeConnection);
        $clients->claimNickname($john, 'John');
        $clients->claimNickname($jane, 'Jane');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $lifecycle->disconnected($john);

        $this->assertCount(1, $janeConnection->messages);
        $this->assertSame('John', $janeConnection->messages[0]->source);
        $this->assertSame('QUIT', $janeConnection->messages[0]->command);
        $this->assertSame(['Connection closed'], $janeConnection->messages[0]->parameters);
    }

    /** @return array{ClientConnectionLifecycle, ClientRegistry, ChannelRegistry} */
    private function lifecycle(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new ClientConnectionLifecycle(
                clients: $clients,
                departure: new ClientDeparture(
                    clients: $clients,
                    channels: $channels,
                    broadcaster: new ChannelBroadcaster($clients, $channels),
                ),
            ),
            $clients,
            $channels,
        ];
    }
}
