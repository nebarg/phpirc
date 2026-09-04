<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport\Time;

use PhpIrc\Irc\Transport\Time\MonotonicClock;

final class ManualMonotonicClock implements MonotonicClock
{
    public function __construct(
        private float $time = 0.0,
    ) {}

    public function now(): float
    {
        return $this->time;
    }

    public function advance(float $seconds): void
    {
        $this->time += $seconds;
    }
}
