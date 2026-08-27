<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;

final class ClientConnection implements Connection
{
    public function __construct(
        private readonly ClientSocket $socket,
        private readonly LineBuffer $buffer,
        private readonly MessageParser $parser,
        private readonly MessageEncoder $encoder,
        private readonly MessageHandler $handler,
    ) {}

    public function run(): void
    {
        try {
            while (($bytes = $this->socket->read()) !== null) {
                foreach ($this->buffer->push($bytes) as $line) {
                    try {
                        $message = $this->parser->parse($line);
                    } catch (InvalidMessageException) {
                        continue;
                    }

                    $this->handler->handle($this, $message);
                }
            }
        } finally {
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
