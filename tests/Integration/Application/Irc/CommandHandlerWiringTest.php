<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Application\Irc\CommandHandlerRegistry;
use PhpIrc\Irc\Command\CapHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Command\NickHandler;
use PhpIrc\Irc\Command\PingHandler;
use PhpIrc\Irc\Command\UserHandler;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Command\RecordingCommandHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\RecordingConnection;

final class CommandHandlerWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_discovers_application_command_handlers(): void
    {
        $handlers = $this->container
            ->get(CommandHandlerRegistry::class)
            ->all();

        $this->assertContains(PingHandler::class, $handlers);
        $this->assertContains(NickHandler::class, $handlers);
        $this->assertContains(UserHandler::class, $handlers);
        $this->assertContains(CapHandler::class, $handlers);
        $this->assertNotContains(RecordingCommandHandler::class, $handlers);
        $this->assertSame($handlers, array_values(array_unique($handlers)));
    }

    #[Test]
    public function it_resolves_the_server_name_from_the_server_configuration(): void
    {
        $config = $this->container->get(ServerConfig::class);
        $serverName = $this->container->get(ServerName::class);

        $this->assertSame($config->serverName, $serverName);
    }

    #[Test]
    public function it_builds_the_message_handler_from_discovered_commands(): void
    {
        $handler = $this->container->get(MessageHandler::class);
        $connection = new RecordingConnection();
        $serverName = $this->container->get(ServerName::class);

        $handler->handle(
            new CommandContext($connection, new Client()),
            new Message(
                command: 'PING',
                parameters: ['registry-token'],
            ),
        );

        $this->assertInstanceOf(CommandDispatcher::class, $handler);
        $this->assertCount(1, $connection->messages);
        $this->assertSame($serverName->value, $connection->messages[0]->source);
        $this->assertSame('PONG', $connection->messages[0]->command);
        $this->assertSame(
            [$serverName->value, 'registry-token'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_handles_a_raw_ping_through_the_complete_connection_slice(): void
    {
        $socket = new FakeClientSocket(["PING :slice-token\r\n"]);
        $serverName = $this->container->get(ServerName::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [":{$serverName->value} PONG {$serverName->value} slice-token\r\n"],
            $socket->writes,
        );
        $this->assertSame(1, $socket->closeCalls);
    }

    #[Test]
    public function it_registers_a_raw_client_with_nick_and_user(): void
    {
        $socket = new FakeClientSocket([
            "NICK Grant\r\nUSER grant 0 * :Grant Burrows\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            $this->registrationWrites($config),
            $socket->writes,
        );
    }

    #[Test]
    public function it_delays_raw_client_registration_until_cap_end(): void
    {
        $socket = new FakeClientSocket([
            "CAP LS 302\r\nNICK Grant\r\nUSER grant 0 * :Grant Burrows\r\nCAP END\r\nCAP END\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ":{$config->serverName->value} CAP * LS :\r\n",
                ...$this->registrationWrites($config),
            ],
            $socket->writes,
        );
    }

    /** @return list<string> */
    private function registrationWrites(ServerConfig $config): array
    {
        $serverName = $config->serverName->value;

        return [
            ":{$serverName} 001 Grant :Welcome to the {$config->networkName} Network, Grant\r\n",
            ":{$serverName} 002 Grant :Your host is {$serverName}, running version {$config->softwareVersion}\r\n",
            ":{$serverName} 003 Grant :This server was created {$config->startedAt->format(\DateTimeInterface::ATOM)}\r\n",
            ":{$serverName} 004 Grant {$serverName} {$config->softwareVersion} - -\r\n",
            ":{$serverName} 005 Grant CASEMAPPING=rfc1459 NICKLEN=30 NETWORK={$config->networkName} :are supported by this server\r\n",
            ":{$serverName} 422 Grant :MOTD File is missing\r\n",
        ];
    }
}
