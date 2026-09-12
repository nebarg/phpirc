<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Transport\ConnectionStatistics;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ConnectionStatisticsTest extends TestCase
{
    #[Test]
    public function it_tracks_received_and_peak_connections_for_the_current_server_run(): void
    {
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $statistics = new ConnectionStatistics($clients);
        $this->connect($clients);
        $statistics->connectionAccepted();
        $jane = $this->connect($clients);
        $statistics->connectionAccepted();

        $clients->unregister($jane);
        $this->connect($clients);
        $statistics->connectionAccepted();

        $this->assertSame(2, $statistics->peakConnections);
        $this->assertSame(3, $statistics->connectionsReceived);
    }

    #[Test]
    public function it_tracks_the_peak_number_of_registered_clients(): void
    {
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $statistics = new ConnectionStatistics($clients);
        $this->connect($clients, 'John');
        $statistics->clientRegistered();
        $jane = $this->connect($clients, 'Jane');
        $statistics->clientRegistered();

        $clients->unregister($jane);
        $this->connect($clients, 'Joe');
        $statistics->clientRegistered();

        $this->assertSame(2, $statistics->peakRegisteredClients);
    }

    private function connect(ClientRegistry $clients, ?string $nickname = null): Client
    {
        $client = new Client();
        $clients->register($client, new RecordingConnection());

        if ($nickname === null) {
            return $client;
        }

        $clients->claimNickname($client, $nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();

        return $client;
    }
}
