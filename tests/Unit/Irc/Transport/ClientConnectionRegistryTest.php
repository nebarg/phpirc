<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Transport\ClientConnectionRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ClientConnectionRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_and_finds_a_clients_connection(): void
    {
        $registry = new ClientConnectionRegistry();
        $client = new Client();
        $connection = new RecordingConnection();

        $registry->register($client, $connection);

        $this->assertSame($connection, $registry->find($client));
    }

    #[Test]
    public function it_distinguishes_clients_by_object_identity(): void
    {
        $registry = new ClientConnectionRegistry();
        $firstClient = new Client();
        $secondClient = new Client();
        $firstConnection = new RecordingConnection();
        $secondConnection = new RecordingConnection();

        $registry->register($firstClient, $firstConnection);
        $registry->register($secondClient, $secondConnection);

        $this->assertSame($firstConnection, $registry->find($firstClient));
        $this->assertSame($secondConnection, $registry->find($secondClient));
    }

    #[Test]
    public function it_unregisters_the_matching_connection(): void
    {
        $registry = new ClientConnectionRegistry();
        $client = new Client();
        $connection = new RecordingConnection();
        $registry->register($client, $connection);

        $registry->unregister($client, $connection);

        $this->assertNull($registry->find($client));
    }

    #[Test]
    public function it_does_not_unregister_a_different_connection(): void
    {
        $registry = new ClientConnectionRegistry();
        $client = new Client();
        $registered = new RecordingConnection();
        $other = new RecordingConnection();
        $registry->register($client, $registered);

        $registry->unregister($client, $other);

        $this->assertSame($registered, $registry->find($client));
    }
}
