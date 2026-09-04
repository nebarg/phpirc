<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PhpIrc\Irc\Transport\Flood\FloodProtectionFactory;
use PhpIrc\Irc\Transport\Keepalive\ConnectionKeepaliveFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\Time\ManualMonotonicClock;
use Tests\Support\Irc\Transport\Timer\ManualTimerScheduler;
use Tests\TestCase;

final class ClientConnectionFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_new_connection_for_each_socket(): void
    {
        $factory = $this->factory(new RecordingMessageHandler());

        $first = $factory->create(new FakeClientSocket());
        $second = $factory->create(new FakeClientSocket());

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_creates_a_new_client_for_each_connection(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory($handler);

        $factory->create(new FakeClientSocket(["PING :one\r\n"]))->run();
        $factory->create(new FakeClientSocket(["PING :two\r\n"]))->run();

        $this->assertCount(2, $handler->contexts);
        $this->assertNotSame(
            $handler->contexts[0]->client,
            $handler->contexts[1]->client,
        );
    }

    #[Test]
    public function it_assigns_the_socket_remote_address_to_the_client_hostname(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory($handler);

        $factory->create(new FakeClientSocket(
            chunks: ["PING :one\r\n"],
            remoteAddress: '203.0.113.10',
        ))->run();

        $this->assertSame('203.0.113.10', $handler->contexts[0]->client->hostname);
    }

    #[Test]
    public function it_does_not_share_buffered_input_between_connections(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory($handler);

        $factory->create(new FakeClientSocket([
            'PRIVMSG #php :unfinished',
        ]))->run();

        $factory->create(new FakeClientSocket([
            "PING :token\r\n",
        ]))->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PING', $handler->messages[0]->command);
        $this->assertSame(['token'], $handler->messages[0]->parameters);
    }

    #[Test]
    public function it_does_not_share_flood_limits_between_connections(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory(
            handler: $handler,
            floodProtection: new FloodProtectionConfig(
                burstMessages: 1,
                messagesPerSecond: 1,
            ),
        );

        $factory->create(new FakeClientSocket(["PING :one\r\n"]))->run();
        $factory->create(new FakeClientSocket(["PING :two\r\n"]))->run();

        $this->assertCount(2, $handler->messages);
    }

    private function factory(
        RecordingMessageHandler $handler,
        ?FloodProtectionConfig $floodProtection = null,
    ): ClientConnectionFactory {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [],
            floodProtection: $floodProtection ?? new FloodProtectionConfig(),
        );

        return new ClientConnectionFactory(
            validator: new ClientMessageSizeValidator(),
            parser: new MessageParser(),
            encoder: new MessageEncoder(),
            handler: $handler,
            lifecycle: new ClientConnectionLifecycle(
                clients: $clients,
                departure: new ClientDeparture(
                    clients: $clients,
                    channels: $channels,
                    broadcaster: new ChannelBroadcaster($clients, $channels),
                ),
            ),
            keepalives: new ConnectionKeepaliveFactory(
                timers: new ManualTimerScheduler(),
                config: $config,
            ),
            floodProtection: new FloodProtectionFactory(
                clock: new ManualMonotonicClock(),
                config: $config,
            ),
        );
    }
}
