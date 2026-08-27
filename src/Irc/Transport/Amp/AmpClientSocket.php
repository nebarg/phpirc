<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\ByteStream\StreamException;
use Amp\Socket\Socket as AmpSocket;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\ClientSocketException;

final readonly class AmpClientSocket implements ClientSocket
{
    public function __construct(
        private AmpSocket $socket,
    ) {}

    public function read(): ?string
    {
        try {
            return $this->socket->read();
        } catch (StreamException $exception) {
            throw new ClientSocketException(
                'Failed to read from the client socket.',
                previous: $exception,
            );
        }
    }

    public function write(string $bytes): void
    {
        try {
            $this->socket->write($bytes);
        } catch (StreamException $exception) {
            throw new ClientSocketException(
                'Failed to write to the client socket.',
                previous: $exception,
            );
        }
    }

    public function close(): void
    {
        $this->socket->close();
    }
}
