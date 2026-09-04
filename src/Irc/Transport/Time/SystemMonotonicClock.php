<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Time;

use function hrtime;

final readonly class SystemMonotonicClock implements MonotonicClock
{
    public function now(): float
    {
        return hrtime(as_number: true) / 1_000_000_000;
    }
}
