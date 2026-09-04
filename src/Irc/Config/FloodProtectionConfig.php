<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class FloodProtectionConfig
{
    public function __construct(
        public int $burstMessages = 20,
        public int $messagesPerSecond = 2,
    ) {
        if ($burstMessages < 1) {
            throw new InvalidArgumentException('Message burst must be at least one.');
        }

        if ($messagesPerSecond < 1) {
            throw new InvalidArgumentException('Message rate must be at least one per second.');
        }
    }
}
