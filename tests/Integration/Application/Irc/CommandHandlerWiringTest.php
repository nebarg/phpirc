<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Application\Irc\CommandHandlerRegistry;
use PhpIrc\Irc\Channel\Command\JoinHandler;
use PhpIrc\Irc\Channel\Command\KickHandler;
use PhpIrc\Irc\Channel\Command\ListHandler;
use PhpIrc\Irc\Channel\Command\NamesHandler;
use PhpIrc\Irc\Channel\Command\PartHandler;
use PhpIrc\Irc\Channel\Command\TopicHandler;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\CapHandler;
use PhpIrc\Irc\Client\Command\LusersHandler;
use PhpIrc\Irc\Client\Command\MotdHandler;
use PhpIrc\Irc\Client\Command\NickHandler;
use PhpIrc\Irc\Client\Command\PingHandler;
use PhpIrc\Irc\Client\Command\PongHandler;
use PhpIrc\Irc\Client\Command\QuitHandler;
use PhpIrc\Irc\Client\Command\UserHandler;
use PhpIrc\Irc\Client\Command\WhoHandler;
use PhpIrc\Irc\Client\Command\WhoisHandler;
use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Message\Command\NoticeHandler;
use PhpIrc\Irc\Message\Command\PrivmsgHandler;
use PhpIrc\Irc\Mode\Command\ModeHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Command\RecordingCommandHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\Support\Irc\Transport\Task\ImmediateBackgroundTaskRunner;

final class CommandHandlerWiringTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->singleton(
            BackgroundTaskRunner::class,
            static fn () => new ImmediateBackgroundTaskRunner(),
        );
    }

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
        $this->assertContains(LusersHandler::class, $handlers);
        $this->assertContains(MotdHandler::class, $handlers);
        $this->assertContains(WhoHandler::class, $handlers);
        $this->assertContains(WhoisHandler::class, $handlers);
        $this->assertContains(JoinHandler::class, $handlers);
        $this->assertContains(KickHandler::class, $handlers);
        $this->assertContains(ListHandler::class, $handlers);
        $this->assertContains(NamesHandler::class, $handlers);
        $this->assertContains(PartHandler::class, $handlers);
        $this->assertContains(TopicHandler::class, $handlers);
        $this->assertContains(PrivmsgHandler::class, $handlers);
        $this->assertContains(NoticeHandler::class, $handlers);
        $this->assertContains(ModeHandler::class, $handlers);
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
    public function it_disconnects_a_raw_client_that_exceeds_the_message_burst(): void
    {
        $config = $this->container->get(ServerConfig::class);
        $messages = [];

        for ($index = 1; $index <= ($config->floodProtection->burstMessages + 1); $index++) {
            $messages[] = "PING :token-{$index}\r\n";
        }

        $socket = new FakeClientSocket([implode('', $messages)]);

        $this->container
            ->get(ClientConnectionFactory::class)
            ->create($socket)
            ->run();

        $this->assertCount($config->floodProtection->burstMessages + 1, $socket->writes);
        $this->assertSame(
            ":{$config->serverName->value} ERROR :Excess flood\r\n",
            $socket->writes[$config->floodProtection->burstMessages],
        );
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
            ":{$serverName} 004 John {$serverName} {$config->softwareVersion} i mntov\r\n",
            ":{$serverName} 005 John CASEMAPPING=ascii CHANMODES=,,,mnt CHANTYPES=# CHANNELLEN=64 HOSTLEN=63 NICKLEN=30 NETWORK={$config->networkName} PREFIX=(ov)@+ TOPICLEN=307 USERLEN=18 :are supported by this server\r\n",
            ":{$serverName} 251 John :There are 1 users and 0 invisible on 1 servers\r\n",
            ":{$serverName} 255 John :I have 1 clients and 0 servers\r\n",
            ...$this->motdWrites($config, 'John'),
        ];
    }

    /** @return list<string> */
    private function motdWrites(ServerConfig $config, string $target): array
    {
        $serverName = $config->serverName->value;
        $motd = $this->container->get(Motd::class);

        if ($motd->isEmpty()) {
            return [":{$serverName} 422 {$target} :MOTD File is missing\r\n"];
        }

        $writes = [
            ":{$serverName} 375 {$target} :- {$serverName} Message of the day -\r\n",
        ];

        foreach ($motd->lines as $line) {
            $writes[] = ":{$serverName} 372 {$target} :- {$line}\r\n";
        }

        $writes[] = ":{$serverName} 376 {$target} :End of /MOTD command.\r\n";

        return $writes;
    }
}
