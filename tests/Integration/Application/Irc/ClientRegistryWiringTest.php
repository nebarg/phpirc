<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\ConnectionStatistics;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Transport\RecordingConnection;

final class ClientRegistryWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_registers_the_client_registry_as_a_singleton(): void
    {
        $first = $this->container->get(ClientRegistry::class);
        $second = $this->container->get(ClientRegistry::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_registers_connection_statistics_as_a_singleton(): void
    {
        $first = $this->container->get(ConnectionStatistics::class);
        $second = $this->container->get(ConnectionStatistics::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_builds_the_registration_completer_from_server_configuration(): void
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');
        $connection = new RecordingConnection();

        $this->container
            ->get(RegistrationCompleter::class)
            ->completeIfReady(new CommandContext($connection, $client));

        $config = $this->container->get(ServerConfig::class);
        $motd = $this->container->get(Motd::class);
        $motdCommands = $motd->isEmpty()
            ? ['422']
            : ['375', ...array_fill(0, count($motd->lines), '372'), '376'];

        $this->assertCount(10 + count($motdCommands), $connection->messages);
        $this->assertSame($config->serverName->value, $connection->messages[0]->source);
        $this->assertSame(
            [
                '001',
                '002',
                '003',
                '004',
                '005',
                '251',
                '255',
                '265',
                '266',
                '250',
                ...$motdCommands,
            ],
            array_column($connection->messages, 'command'),
        );
        $this->assertStringContainsString(
            $config->networkName,
            $connection->messages[0]->parameters[1],
        );
    }
}
