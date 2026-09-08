<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class OutboundQueueConfig
{
    public function __construct(
        public int $maximumBytes = 262_144,
    ) {
        if ($maximumBytes < 1) {
            throw new InvalidArgumentException('Maximum outbound queue bytes must be at least one.');
        }
    }
}
