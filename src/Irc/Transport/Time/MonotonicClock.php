<?php

namespace PhpIrc\Irc\Transport\Time;

interface MonotonicClock
{
    public function now(): float;
}
