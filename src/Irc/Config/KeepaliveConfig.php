<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class KeepaliveConfig
{
    public function __construct(
        public int $pingIntervalSeconds = 120,
        public int $pongTimeoutSeconds = 30,
    ) {
        if ($pingIntervalSeconds < 1) {
            throw new InvalidArgumentException('Ping interval must be at least one second.');
        }

        if ($pongTimeoutSeconds < 1) {
            throw new InvalidArgumentException('PONG timeout must be at least one second.');
        }
    }
}
