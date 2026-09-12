<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Time;

use DateTimeImmutable;
use PhpIrc\Irc\Time\WallClock;

final class ManualWallClock implements WallClock
{
    public function __construct(
        public DateTimeImmutable $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
