<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\Socket\ServerSocket as AmpServerSocket;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientSocket;

final readonly class AmpClientListener implements ClientListener
{
    public function __construct(
        private AmpServerSocket $server,
    ) {}

    public function accept(): ?ClientSocket
    {
        $socket = $this->server->accept();

        return $socket === null ? null : new AmpClientSocket($socket);
    }

    public function close(): void
    {
        $this->server->close();
    }
}
