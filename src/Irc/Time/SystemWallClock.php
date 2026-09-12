<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Time;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemWallClock implements WallClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
