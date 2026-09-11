<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class ListenerTlsConfig
{
    public function __construct(
        public string $certificateFile,
        public string $privateKeyFile,
        public int $handshakeTimeoutSeconds = 10,
    ) {
        if ($certificateFile === '') {
            throw new InvalidArgumentException('TLS certificate file cannot be empty.');
        }

        if ($privateKeyFile === '') {
            throw new InvalidArgumentException('TLS private key file cannot be empty.');
        }

        if ($handshakeTimeoutSeconds < 1) {
            throw new InvalidArgumentException('TLS handshake timeout must be at least one second.');
        }
    }
}
