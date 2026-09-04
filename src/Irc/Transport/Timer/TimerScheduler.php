<?php

namespace PhpIrc\Irc\Transport\Timer;

use Closure;

interface TimerScheduler
{
    /** @param Closure(): void $callback */
    public function delay(float $seconds, Closure $callback): string;

    public function cancel(string $timerId): void;
}
