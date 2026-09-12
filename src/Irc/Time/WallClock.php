<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Time;

use DateTimeImmutable;

interface WallClock
{
    public function now(): DateTimeImmutable;
}
