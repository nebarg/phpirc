<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Config\OutboundQueueConfig;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\OutboundMessageQueue;
use PhpIrc\Irc\Transport\OutboundQueueFullException;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\Task\ImmediateBackgroundTaskRunner;
use Tests\Support\Irc\Transport\Task\ManualBackgroundTaskRunner;
use Tests\TestCase;

final class OutboundMessageQueueTest extends TestCase
{
    #[Test]
    public function it_drains_messages_in_the_order_they_were_enqueued(): void
    {
        $socket = new FakeClientSocket();
        $tasks = new ManualBackgroundTaskRunner();
        $queue = $this->queue($socket, $tasks);

        $queue->enqueue('first');
        $queue->enqueue('second');

        $this->assertSame([], $socket->writes);
        $this->assertSame(11, $queue->queuedBytes());
        $this->assertSame(1, $tasks->pendingCount());

        $tasks->runNext();

        $this->assertSame(['first', 'second'], $socket->writes);
        $this->assertSame(0, $queue->queuedBytes());
        $this->assertSame(0, $tasks->pendingCount());
    }

    #[Test]
    public function it_accepts_messages_up_to_the_exact_byte_limit(): void
    {
        $socket = new FakeClientSocket();
        $tasks = new ManualBackgroundTaskRunner();
        $queue = $this->queue($socket, $tasks, maximumBytes: 5);

        $queue->enqueue('123');
        $queue->enqueue('45');

        $this->assertSame(5, $queue->queuedBytes());

        $tasks->runNext();

        $this->assertSame(['123', '45'], $socket->writes);
    }

    #[Test]
    public function it_rejects_a_message_that_would_exceed_the_byte_limit(): void
    {
        $socket = new FakeClientSocket();
        $tasks = new ManualBackgroundTaskRunner();
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Disconnecting slow IRC client because its outbound queue is full.',
                [
                    'remote_address' => '127.0.0.1',
                    'queued_bytes' => 4,
                    'message_bytes' => 2,
                    'limit' => 5,
                ],
            );
        $queue = $this->queue($socket, $tasks, maximumBytes: 5, logger: $logger);
        $queue->enqueue('1234');

        try {
            $queue->enqueue('56');
            $this->fail('Expected an outbound queue full exception.');
        } catch (OutboundQueueFullException $exception) {
            $this->assertSame('Outbound message queue is full.', $exception->getMessage());
            $this->assertSame(4, $queue->queuedBytes());
            $this->assertSame(0, $socket->closeCalls);
        }
    }

    #[Test]
    public function it_releases_capacity_after_messages_are_written(): void
    {
        $socket = new FakeClientSocket();
        $queue = $this->queue(
            socket: $socket,
            tasks: new ImmediateBackgroundTaskRunner(),
            maximumBytes: 5,
        );

        $queue->enqueue('12345');
        $queue->enqueue('67890');

        $this->assertSame(['12345', '67890'], $socket->writes);
        $this->assertSame(0, $queue->queuedBytes());
    }

    #[Test]
    public function it_discards_queued_messages_when_forcefully_closed(): void
    {
        $socket = new FakeClientSocket();
        $tasks = new ManualBackgroundTaskRunner();
        $queue = $this->queue($socket, $tasks);
        $queue->enqueue('message');

        $queue->discardAndClose();
        $queue->enqueue('ignored');
        $tasks->runNext();

        $this->assertSame([], $socket->writes);
        $this->assertSame(0, $queue->queuedBytes());
        $this->assertSame(1, $socket->closeCalls);
    }

    #[Test]
    public function it_writes_queued_messages_before_gracefully_closing(): void
    {
        $socket = new FakeClientSocket();
        $tasks = new ManualBackgroundTaskRunner();
        $queue = $this->queue($socket, $tasks);
        $queue->enqueue('message');

        $queue->finishAndClose();

        $this->assertSocketIsOpen($socket);

        $tasks->runNext();

        $this->assertSame(['message'], $socket->writes);
        $this->assertSame(1, $socket->closeCalls);
        $this->assertSame(0, $queue->queuedBytes());
    }

    #[Test]
    public function it_isolates_a_queued_write_failure_and_closes_the_socket(): void
    {
        $failure = new ClientSocketException('Write failed.');
        $socket = new FakeClientSocket(writeException: $failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to write an outbound IRC message.',
                [
                    'remote_address' => '127.0.0.1',
                    'exception' => $failure,
                ],
            );
        $queue = $this->queue(
            socket: $socket,
            tasks: new ImmediateBackgroundTaskRunner(),
            logger: $logger,
        );

        $queue->enqueue('message');
        $queue->enqueue('ignored');

        $this->assertSame(1, $socket->closeCalls);
        $this->assertSame([], $socket->writes);
        $this->assertSame(0, $queue->queuedBytes());
    }

    private function queue(
        FakeClientSocket $socket,
        ManualBackgroundTaskRunner|ImmediateBackgroundTaskRunner $tasks,
        int $maximumBytes = 262_144,
        ?LoggerInterface $logger = null,
    ): OutboundMessageQueue {
        return new OutboundMessageQueue(
            socket: $socket,
            tasks: $tasks,
            config: new OutboundQueueConfig($maximumBytes),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function assertSocketIsOpen(FakeClientSocket $socket): void
    {
        $this->assertSame(0, $socket->closeCalls);
    }
}
