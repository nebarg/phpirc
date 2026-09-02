<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
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

    /** @return array{ClientConnectionLifecycle, ClientRegistry, ChannelRegistry} */
    private function lifecycle(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new ClientConnectionLifecycle($clients, $channels),
            $clients,
            $channels,
        ];
    }
}
