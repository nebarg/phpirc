<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Revolt;

use Closure;
use PhpIrc\Irc\Transport\Timer\TimerScheduler;
use Revolt\EventLoop;

final readonly class RevoltTimerScheduler implements TimerScheduler
{
    public function delay(float $seconds, Closure $callback): string
    {
        return EventLoop::delay(
            $seconds,
            static function (string $unusedTimerId) use ($callback): void {
                $callback();
            },
        );
    }

    public function cancel(string $timerId): void
    {
        EventLoop::cancel($timerId);
    }
}
