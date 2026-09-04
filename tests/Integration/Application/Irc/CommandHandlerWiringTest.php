<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Application\Irc\CommandHandlerRegistry;
use PhpIrc\Irc\Channel\Command\JoinHandler;
use PhpIrc\Irc\Channel\Command\ListHandler;
use PhpIrc\Irc\Channel\Command\NamesHandler;
use PhpIrc\Irc\Channel\Command\PartHandler;
use PhpIrc\Irc\Channel\Command\TopicHandler;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\CapHandler;
use PhpIrc\Irc\Client\Command\NickHandler;
use PhpIrc\Irc\Client\Command\PingHandler;
use PhpIrc\Irc\Client\Command\PongHandler;
use PhpIrc\Irc\Client\Command\QuitHandler;
use PhpIrc\Irc\Client\Command\UserHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Message\Command\NoticeHandler;
use PhpIrc\Irc\Message\Command\PrivmsgHandler;
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
        $this->assertContains(PongHandler::class, $handlers);
        $this->assertContains(QuitHandler::class, $handlers);
        $this->assertContains(NickHandler::class, $handlers);
        $this->assertContains(UserHandler::class, $handlers);
        $this->assertContains(CapHandler::class, $handlers);
        $this->assertContains(JoinHandler::class, $handlers);
        $this->assertContains(ListHandler::class, $handlers);
        $this->assertContains(NamesHandler::class, $handlers);
        $this->assertContains(PartHandler::class, $handlers);
        $this->assertContains(TopicHandler::class, $handlers);
        $this->assertContains(PrivmsgHandler::class, $handlers);
        $this->assertContains(NoticeHandler::class, $handlers);
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
    public function it_accepts_a_raw_pong_before_registration(): void
    {
        $socket = new FakeClientSocket(["PONG :unsolicited-token\r\n"]);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame([], $socket->writes);
        $this->assertSame(1, $socket->closeCalls);
    }

    #[Test]
    public function it_registers_a_raw_client_with_nick_and_user(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\n",
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
            "CAP LS 302\r\nNICK John\r\nUSER john 0 * :John Doe\r\nCAP END\r\nCAP END\r\n",
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

    #[Test]
    public function it_handles_a_raw_join_after_registration(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nJOIN #php\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);
        $serverName = $config->serverName->value;

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John JOIN #php\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_raw_names_for_an_existing_channel(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nJOIN #php\r\nNAMES #PHP\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);
        $serverName = $config->serverName->value;

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John JOIN #php\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_a_raw_list_after_joining_a_channel(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nJOIN #php\r\nLIST\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);
        $serverName = $config->serverName->value;

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John JOIN #php\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
                ":{$serverName} 321 John Channel :Users  Name\r\n",
                ":{$serverName} 322 John #php 1 :\r\n",
                ":{$serverName} 323 John :End of /LIST\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_raw_topic_queries_and_changes(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nJOIN #php\r\nTOPIC #PHP\r\nTOPIC #PHP :PHP discussion\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);
        $serverName = $config->serverName->value;

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John JOIN #php\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
                ":{$serverName} 331 John #php :No topic is set\r\n",
                ":John TOPIC #php :PHP discussion\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_a_raw_part_after_joining_a_channel(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nJOIN #php\r\nPART #php\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);
        $serverName = $config->serverName->value;

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John JOIN #php\r\n",
                ":{$serverName} 353 John = #php @John\r\n",
                ":{$serverName} 366 John #php :End of /NAMES list\r\n",
                ":John PART #php\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_a_raw_private_message_to_the_registered_client(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nPRIVMSG john :A note to myself\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John PRIVMSG John :A note to myself\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_a_raw_notice_to_the_registered_client(): void
    {
        $socket = new FakeClientSocket([
            "NOTICE Missing :Ignored before registration\r\nNICK John\r\nUSER john 0 * :John Doe\r\nNOTICE john :A quiet note\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":John NOTICE John :A quiet note\r\n",
            ],
            $socket->writes,
        );
    }

    #[Test]
    public function it_handles_a_raw_quit_and_stops_dispatching_messages(): void
    {
        $socket = new FakeClientSocket([
            "NICK John\r\nUSER john 0 * :John Doe\r\nQUIT :Gone for lunch\r\nPING :ignored\r\n",
        ]);
        $config = $this->container->get(ServerConfig::class);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertSame(
            [
                ...$this->registrationWrites($config),
                ":{$config->serverName->value} ERROR :Closing Link: John (Quit: Gone for lunch)\r\n",
            ],
            $socket->writes,
        );
        $this->assertSame(1, $socket->closeCalls);
    }

    /** @return list<string> */
    private function registrationWrites(ServerConfig $config): array
    {
        $serverName = $config->serverName->value;

        return [
            ":{$serverName} 001 John :Welcome to the {$config->networkName} Network, John\r\n",
            ":{$serverName} 002 John :Your host is {$serverName}, running version {$config->softwareVersion}\r\n",
            ":{$serverName} 003 John :This server was created {$config->startedAt->format(\DateTimeInterface::ATOM)}\r\n",
            ":{$serverName} 004 John {$serverName} {$config->softwareVersion} - -\r\n",
            ":{$serverName} 005 John CASEMAPPING=ascii CHANTYPES=# CHANNELLEN=64 NICKLEN=30 NETWORK={$config->networkName} PREFIX=(o)@ :are supported by this server\r\n",
            ":{$serverName} 422 John :MOTD File is missing\r\n",
        ];
    }
}
