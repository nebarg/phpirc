<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Keepalive\ConnectionKeepalive;

final class ClientConnection implements Connection
{
    private bool $closed = false;

    private string $disconnectReason = 'Connection closed';

    public function __construct(
        private readonly Client $client,
        private readonly ClientSocket $socket,
        private readonly MessageCodec $codec,
        private readonly MessageHandler $handler,
        private readonly ClientConnectionLifecycle $lifecycle,
        private readonly ConnectionKeepalive $keepalive,
        private readonly OutboundMessageQueue $outboundQueue,
    ) {}

    public function run(): void
    {
        $registered = false;

        try {
            $this->lifecycle->connected($this->client, $this);
            $registered = true;
            $this->keepalive->start($this);

            $context = new CommandContext(
                connection: $this,
                client: $this->client,
            );

            while (($bytes = $this->socket->read()) !== null) {
                foreach ($this->codec->decode($bytes) as $message) {
                    $this->keepalive->activityReceived();
                    $this->handler->handle($context, $message);

                    if ($this->closed) {
                        break 2;
                    }
                }
            }
        } finally {
            $this->keepalive->stop();
            $this->close();

            if ($registered) {
                $this->lifecycle->disconnected($this->client, $this->disconnectReason);
            }
        }
    }

    public function send(Message $message): void
    {
        if ($this->closed) {
            return;
        }

        $encoded = $this->codec->encode($message);

        if ($encoded === null) {
            return;
        }

        try {
            $this->outboundQueue->enqueue($encoded);
        } catch (OutboundQueueFullException) {
            // IRC lingo for exceeding the outbound queue limit.
            $this->closeImmediately('SendQ exceeded');
        }
    }

    public function sendMany(iterable $messages): void
    {
        foreach ($messages as $message) {
            $this->send($message);
        }
    }

    public function close(string $reason = 'Connection closed'): void
    {
        if (! $this->beginClosing($reason)) {
            return;
        }

        $this->outboundQueue->finishAndClose();
    }

    public function pongReceived(string $token): void
    {
        $this->keepalive->pongReceived($token);
    }

    private function closeImmediately(string $reason): void
    {
        if (! $this->beginClosing($reason)) {
            return;
        }

        $this->outboundQueue->discardAndClose();
    }

    private function beginClosing(string $reason): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;
        $this->disconnectReason = $reason;

        return true;
    }
}
