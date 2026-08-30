<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;

final class ClientConnection implements Connection
{
    public function __construct(
        private readonly Client $client,
        private readonly ClientRegistry $clients,
        private readonly ClientSocket $socket,
        private readonly LineBuffer $buffer,
        private readonly MessageParser $parser,
        private readonly MessageEncoder $encoder,
        private readonly MessageHandler $handler,
    ) {}

    public function run(): void
    {
        try {
            $context = new CommandContext($this, $this->client);

            while (($bytes = $this->socket->read()) !== null) {
                foreach ($this->buffer->push($bytes) as $line) {
                    try {
                        $message = $this->parser->parse($line);
                    } catch (InvalidMessageException) {
                        continue;
                    }

                    $this->handler->handle($context, $message);
                }
            }
        } finally {
            $this->clients->release($this->client);
            $this->close();
        }
    }

    public function send(Message $message): void
    {
        $this->socket->write(
            $this->encoder->encode($message),
        );
    }

    public function close(): void
    {
        $this->socket->close();
    }
}
