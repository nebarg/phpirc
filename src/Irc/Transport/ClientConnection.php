<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\Message;

final class ClientConnection implements Connection
{
    private bool $closed = false;

    public function __construct(
        private readonly Client $client,
        private readonly ClientSocket $socket,
        private readonly MessageCodec $codec,
        private readonly MessageHandler $handler,
        private readonly ClientConnectionLifecycle $lifecycle,
    ) {}

    public function run(): void
    {
        $registered = false;

        try {
            $this->lifecycle->connected($this->client, $this);
            $registered = true;

            $context = new CommandContext(
                connection: $this,
                client: $this->client,
            );

            while (($bytes = $this->socket->read()) !== null) {
                foreach ($this->codec->decode($bytes) as $message) {
                    $this->handler->handle($context, $message);

                    if ($this->closed) {
                        break 2;
                    }
                }
            }
        } finally {
            try {
                if ($registered) {
                    $this->lifecycle->disconnected($this->client);
                }
            } finally {
                $this->close();
            }
        }
    }

    public function send(Message $message): void
    {
        $this->socket->write(
            $this->codec->encode($message),
        );
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->socket->close();
    }
}
