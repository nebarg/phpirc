<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\Amp\IrcServer;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientSocket;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use RuntimeException;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientListener;
use Tests\Support\Irc\Transport\FakeClientSocket;
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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->server($listener, $handler, $logger)->run();
        EventLoop::run();

        $this->assertSame(3, $listener->acceptCalls);
        $this->assertSame(1, $listener->closeCalls);
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

        $this->server($listener, $handler, $logger)->run();
        EventLoop::run();

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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        try {
            $this->server($listener, $handler, $logger)->run();
            $this->fail('Expected the listener exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Accept failed.', $exception->getMessage());
            $this->assertSame(1, $listener->closeCalls);
        }
    }

    private function server(
        ClientListener $listener,
        RecordingMessageHandler $handler,
        LoggerInterface $logger,
    ): IrcServer {
        return new IrcServer(
            listener: $listener,
            connections: new ClientConnectionFactory(
                validator: new ClientMessageSizeValidator(),
                parser: new MessageParser(),
                encoder: new MessageEncoder(),
                handler: $handler,
                clients: new ClientRegistry(),
            ),
            logger: $logger,
        );
    }
}
