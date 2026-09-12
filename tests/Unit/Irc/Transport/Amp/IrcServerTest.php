<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\SharedChannelPeerBroadcaster;
use PhpIrc\Irc\Client\Capability\ServerTimeMessageTagger;
use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\Amp\IrcServer;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\ConnectionStatistics;
use PhpIrc\Irc\Transport\Flood\FloodProtectionFactory;
use PhpIrc\Irc\Transport\Keepalive\ConnectionKeepaliveFactory;
use PhpIrc\Irc\Transport\OutboundMessagePreparer;
use PhpIrc\Irc\Transport\OutboundMessageQueueFactory;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Time\ManualWallClock;
use Tests\Support\Irc\Transport\BlockingClientSocket;
use Tests\Support\Irc\Transport\FakeClientListener;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\Signal\ManualShutdownSignalListener;
use Tests\Support\Irc\Transport\Task\ImmediateBackgroundTaskRunner;
use Tests\Support\Irc\Transport\Time\ManualMonotonicClock;
use Tests\Support\Irc\Transport\Timer\ManualTimerScheduler;
use Tests\TestCase;

final class IrcServerTest extends TestCase
{
    #[Test]
    public function it_accepts_and_runs_clients_until_the_listener_closes(): void
    {
        $firstSocket = new FakeClientSocket(["PING :one\r\n"]);
        $secondSocket = new FakeClientSocket(["PONG :two\r\n"]);
        $listener = new FakeClientListener([$firstSocket, $secondSocket]);
        $handler = new RecordingMessageHandler();
        $shutdownSignals = new ManualShutdownSignalListener();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->server($listener, $handler, $shutdownSignals, $logger)->run();

        $this->assertSame(3, $listener->acceptCalls);
        $this->assertSame(1, $listener->closeCalls);
        $this->assertSame(1, $shutdownSignals->startCalls);
        $this->assertSame(1, $shutdownSignals->stopCalls);
        $this->assertSame(1, $firstSocket->closeCalls);
        $this->assertSame(1, $secondSocket->closeCalls);
        $this->assertSame(
            ['PING', 'PONG'],
            array_map(
                static fn (Message $message): string => $message->command,
                $handler->messages,
            ),
        );
    }

    #[Test]
    public function it_accepts_clients_from_multiple_listeners(): void
    {
        $firstSocket = new FakeClientSocket(["PING :one\r\n"]);
        $secondSocket = new FakeClientSocket(["PONG :two\r\n"]);
        $firstListener = new FakeClientListener([$firstSocket]);
        $secondListener = new FakeClientListener([$secondSocket]);
        $handler = new RecordingMessageHandler();
        $shutdownSignals = new ManualShutdownSignalListener();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->serverWithListeners(
            [$firstListener, $secondListener],
            $handler,
            $shutdownSignals,
            $logger,
        )->run();

        $this->assertSame(2, $firstListener->acceptCalls);
        $this->assertSame(2, $secondListener->acceptCalls);
        $this->assertSame(1, $firstListener->closeCalls);
        $this->assertSame(1, $secondListener->closeCalls);
        $this->assertSame(1, $firstSocket->closeCalls);
        $this->assertSame(1, $secondSocket->closeCalls);
        $this->assertCount(2, $handler->messages);
        $this->assertSame(
            ['PING', 'PONG'],
            array_map(
                static fn (Message $message): string => $message->command,
                $handler->messages,
            ),
        );
    }

    #[Test]
    public function it_logs_a_client_read_failure_without_preventing_other_clients(): void
    {
        $failure = new RuntimeException('Read failed.');
        $failingSocket = $this->createMock(ClientSocket::class);
        $failingSocket
            ->expects($this->once())
            ->method('read')
            ->willThrowException($failure);
        $failingSocket->expects($this->once())->method('close');

        $healthySocket = new FakeClientSocket(["PING :token\r\n"]);
        $listener = new FakeClientListener([$failingSocket, $healthySocket]);
        $handler = new RecordingMessageHandler();
        $shutdownSignals = new ManualShutdownSignalListener();
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'IRC client connection failed.',
                $this->callback(
                    static fn (array $context): bool => ($context['exception'] ?? null) === $failure,
                ),
            );

        $this->server($listener, $handler, $shutdownSignals, $logger)->run();

        $this->assertSame(1, $healthySocket->closeCalls);
        $this->assertCount(1, $handler->messages);
        $this->assertSame('PING', $handler->messages[0]->command);
    }

    #[Test]
    public function it_closes_the_listener_when_accepting_a_client_fails(): void
    {
        $listener = new class implements ClientListener {
            public int $closeCalls = 0;

            public function accept(): ?ClientSocket
            {
                throw new RuntimeException('Accept failed.');
            }

            public function close(): void
            {
                $this->closeCalls++;
            }
        };
        $handler = new RecordingMessageHandler();
        $shutdownSignals = new ManualShutdownSignalListener();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        try {
            $this->server($listener, $handler, $shutdownSignals, $logger)->run();
            $this->fail('Expected the listener exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Accept failed.', $exception->getMessage());
            $this->assertSame(1, $listener->closeCalls);
            $this->assertSame(1, $shutdownSignals->stopCalls);
        }
    }

    #[Test]
    public function it_stops_accepting_and_notifies_connected_clients_when_shutdown_is_requested(): void
    {
        $shutdownSignals = new ManualShutdownSignalListener();
        $socket = new BlockingClientSocket();
        $listener = new FakeClientListener(
            sockets: [$socket],
            beforeAccept: static function (int $acceptCall) use ($shutdownSignals): void {
                if ($acceptCall === 2) {
                    $shutdownSignals->requestShutdown();
                    $shutdownSignals->requestShutdown();
                }
            },
        );
        $otherListener = new FakeClientListener();
        $handler = new RecordingMessageHandler();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('IRC server shutdown requested.');

        $this->serverWithListeners(
            [$listener, $otherListener],
            $handler,
            $shutdownSignals,
            $logger,
        )->run();

        $this->assertSame(2, $listener->acceptCalls);
        $this->assertSame(1, $listener->closeCalls);
        $this->assertSame(1, $otherListener->closeCalls);
        $this->assertSame(1, $socket->closeCalls);
        $this->assertSame(
            [":irc.test ERROR :Server shutting down\r\n"],
            $socket->writes,
        );
    }

    private function server(
        ClientListener $listener,
        RecordingMessageHandler $handler,
        ShutdownSignalListener $shutdownSignals,
        LoggerInterface $logger,
    ): IrcServer {
        return $this->serverWithListeners(
            [$listener],
            $handler,
            $shutdownSignals,
            $logger,
        );
    }

    /** @param non-empty-list<ClientListener> $listeners */
    private function serverWithListeners(
        array $listeners,
        RecordingMessageHandler $handler,
        ShutdownSignalListener $shutdownSignals,
        LoggerInterface $logger,
    ): IrcServer {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [],
        );

        return new IrcServer(
            listeners: new ClientListenerCollection($listeners),
            connections: new ClientConnectionFactory(
                validator: new ClientMessageSizeValidator(),
                parser: new MessageParser(),
                outboundMessages: new OutboundMessagePreparer(
                    new MessageEncoder(),
                    $logger,
                ),
                handler: $handler,
                lifecycle: new ClientConnectionLifecycle(
                    clients: $clients,
                    departure: new ClientDeparture(
                        clients: $clients,
                        channels: $channels,
                        peers: new SharedChannelPeerBroadcaster($clients, $channels),
                    ),
                    statistics: new ConnectionStatistics($clients),
                ),
                keepalives: new ConnectionKeepaliveFactory(
                    timers: new ManualTimerScheduler(),
                    config: $config,
                ),
                floodProtection: new FloodProtectionFactory(
                    clock: new ManualMonotonicClock(),
                    config: $config,
                ),
                limits: new ServerLimits(new ByteStringTruncator()),
                outboundQueues: new OutboundMessageQueueFactory(
                    tasks: new ImmediateBackgroundTaskRunner(),
                    config: $config,
                    logger: $logger,
                ),
                serverTime: new ServerTimeMessageTagger(
                    new ManualWallClock(new DateTimeImmutable('2026-09-12T12:00:00.000Z')),
                ),
            ),
            shutdownSignals: $shutdownSignals,
            serverName: $config->serverName,
            logger: $logger,
        );
    }
}
