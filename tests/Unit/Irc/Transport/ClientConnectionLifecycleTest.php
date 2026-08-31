<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PhpIrc\Irc\Transport\ClientConnectionRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ClientConnectionLifecycleTest extends TestCase
{
    #[Test]
    public function it_registers_a_connected_client(): void
    {
        [$lifecycle, , $connections] = $this->lifecycle();
        $client = new Client();
        $connection = new RecordingConnection();

        $lifecycle->connected($client, $connection);

        $this->assertSame($connection, $connections->find($client));
    }

    #[Test]
    public function it_releases_all_client_state_when_disconnected(): void
    {
        [$lifecycle, $clients, $connections, $channels] = $this->lifecycle();
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->claimNickname($client, 'John');
        $channels->join('#php', $client);
        $lifecycle->connected($client, $connection);

        $lifecycle->disconnected($client, $connection);

        $this->assertNull($clients->findByNickname('John'));
        $this->assertNull($connections->find($client));
        $this->assertNull($channels->find('#php'));
    }

    /** @return array{ClientConnectionLifecycle, ClientRegistry, ClientConnectionRegistry, ChannelRegistry} */
    private function lifecycle(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $connections = new ClientConnectionRegistry();
        $channels = new ChannelRegistry($caseMapper);

        return [
            new ClientConnectionLifecycle($clients, $connections, $channels),
            $clients,
            $connections,
            $channels,
        ];
    }
}
