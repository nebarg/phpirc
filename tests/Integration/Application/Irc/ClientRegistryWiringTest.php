<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\RegistrationCompleter;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Network\ClientRegistry;
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

        $this->assertCount(6, $connection->messages);
        $this->assertSame($config->serverName->value, $connection->messages[0]->source);
        $this->assertSame(
            ['001', '002', '003', '004', '005', '422'],
            array_map(
                static fn ($message): string => $message->command,
                $connection->messages,
            ),
        );
        $this->assertStringContainsString(
            $config->networkName,
            $connection->messages[0]->parameters[1],
        );
    }
}
