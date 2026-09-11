<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\ByteStream\StreamException;
use Amp\CancelledException;
use Amp\Socket\InternetAddress;
use Amp\Socket\Socket as AmpSocket;
use Amp\Socket\SocketException;
use Amp\Socket\TlsState;
use Amp\TimeoutCancellation;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\ClientSocketException;

final class AmpClientSocket implements ClientSocket
{
    public function __construct(
        private readonly AmpSocket $socket,
        private ?int $tlsHandshakeTimeoutSeconds = null,
    ) {}

    public function remoteAddress(): string
    {
        $address = $this->socket->getRemoteAddress();

        return $address instanceof InternetAddress
            ? $address->getAddress()
            : $address->toString();
    }

    public function read(): ?string
    {
        $this->negotiateTlsIfRequired();

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
        $this->negotiateTlsIfRequired();

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
        try {
            if ($this->socket->getTlsState() === TlsState::Enabled) {
                $this->socket->shutdownTls();
            }
        } catch (StreamException) {
            // The peer may already have disconnected while TLS was shutting down.
        } finally {
            $this->socket->close();
        }
    }

    private function negotiateTlsIfRequired(): void
    {
        $timeout = $this->tlsHandshakeTimeoutSeconds;

        if ($timeout === null) {
            return;
        }

        $this->tlsHandshakeTimeoutSeconds = null;

        try {
            $this->socket->setupTls(new TimeoutCancellation($timeout));
        } catch (CancelledException|SocketException $exception) {
            $this->socket->close();

            throw new ClientSocketException(
                'Failed to negotiate TLS with the client.',
                previous: $exception,
            );
        }
    }
}
