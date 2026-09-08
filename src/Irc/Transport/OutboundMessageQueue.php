<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Config\OutboundQueueConfig;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use Psr\Log\LoggerInterface;
use SplQueue;

final class OutboundMessageQueue
{
    /** @var SplQueue<string> */
    private readonly SplQueue $messages;

    private int $bufferedBytes = 0;

    private int $inFlightBytes = 0;

    private bool $draining = false;

    private bool $accepting = true;

    private bool $closed = false;

    public function __construct(
        private readonly ClientSocket $socket,
        private readonly BackgroundTaskRunner $tasks,
        private readonly OutboundQueueConfig $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->messages = new SplQueue();
    }

    /**
     * @throws OutboundQueueFullException
     */
    public function enqueue(string $message): void
    {
        if (! $this->accepting) {
            return;
        }

        $messageBytes = strlen($message);

        if ($messageBytes > ($this->config->maximumBytes - $this->queuedBytes())) {
            $this->logger->warning(
                'Disconnecting slow IRC client because its outbound queue is full.',
                [
                    'remote_address' => $this->socket->remoteAddress(),
                    'queued_bytes' => $this->queuedBytes(),
                    'message_bytes' => $messageBytes,
                    'limit' => $this->config->maximumBytes,
                ],
            );

            throw new OutboundQueueFullException('Outbound message queue is full.');
        }

        $this->messages->enqueue($message);
        $this->bufferedBytes += $messageBytes;

        $this->startDraining();
    }

    public function finishAndClose(): void
    {
        if ($this->closed || ! $this->accepting) {
            return;
        }

        $this->accepting = false;

        if ($this->queuedBytes() === 0) {
            $this->closeSocket();
        }
    }

    public function discardAndClose(): void
    {
        if ($this->closed) {
            return;
        }

        $this->accepting = false;
        while (! $this->messages->isEmpty()) {
            $this->messages->dequeue();
        }

        $this->bufferedBytes = 0;
        $this->closeSocket();
    }

    public function queuedBytes(): int
    {
        return $this->bufferedBytes + $this->inFlightBytes;
    }

    private function startDraining(): void
    {
        if ($this->closed || $this->draining || $this->messages->isEmpty()) {
            return;
        }

        $this->draining = true;
        $this->tasks->run($this->drain(...));
    }

    private function drain(): void
    {
        try {
            while (! $this->closed && ! $this->messages->isEmpty()) {
                $message = $this->messages->dequeue();
                $messageBytes = strlen($message);
                $this->bufferedBytes -= $messageBytes;
                $this->inFlightBytes = $messageBytes;

                try {
                    $this->socket->write($message);
                } finally {
                    $this->inFlightBytes = 0;
                }
            }
        } catch (ClientSocketException $exception) {
            if ($this->closed) {
                return;
            }

            $this->logger->error(
                'Failed to write an outbound IRC message.',
                [
                    'remote_address' => $this->socket->remoteAddress(),
                    'exception' => $exception,
                ],
            );

            $this->discardAndClose();
        } finally {
            $this->draining = false;

            if (! $this->closed && ! $this->messages->isEmpty()) {
                $this->startDraining();
            } elseif (! $this->accepting && $this->queuedBytes() === 0) {
                $this->closeSocket();
            }
        }
    }

    private function closeSocket(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->socket->close();
    }
}
