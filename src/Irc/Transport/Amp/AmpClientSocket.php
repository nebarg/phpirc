<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\Socket\Socket as AmpSocket;
use PhpIrc\Irc\Transport\ClientSocket;

final readonly class AmpClientSocket implements ClientSocket
{
    public function __construct(
        private AmpSocket $socket,
    ) {}

    public function read(): ?string
    {
        return $this->socket->read();
    }

    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
    }

    public function close(): void
    {
        $this->socket->close();
    }
}
