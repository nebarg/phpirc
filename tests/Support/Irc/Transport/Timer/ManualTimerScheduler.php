<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport\Timer;

use Closure;
use LogicException;
use PhpIrc\Irc\Transport\Timer\TimerScheduler;

final class ManualTimerScheduler implements TimerScheduler
{
    private int $sequence = 0;

    /** @var array<string, Closure(): void> */
    private array $callbacks = [];

    /** @var array<string, float> */
    private array $pendingDelays = [];

    /** @var list<float> */
    public array $scheduledDelays = [];

    /** @var list<string> */
    public array $cancelledTimers = [];

    public function delay(float $seconds, Closure $callback): string
    {
        $timerId = sprintf('timer-%d', ++$this->sequence);

        $this->callbacks[$timerId] = $callback;
        $this->pendingDelays[$timerId] = $seconds;
        $this->scheduledDelays[] = $seconds;

        return $timerId;
    }

    public function cancel(string $timerId): void
    {
        unset($this->callbacks[$timerId], $this->pendingDelays[$timerId]);
        $this->cancelledTimers[] = $timerId;
    }

    public function runNext(): void
    {
        $timerId = array_key_first($this->callbacks);

        if ($timerId === null) {
            throw new LogicException('No timer is waiting to run.');
        }

        $callback = $this->callbacks[$timerId];

        unset($this->callbacks[$timerId], $this->pendingDelays[$timerId]);

        $callback();
    }

    public function pendingCount(): int
    {
        return count($this->callbacks);
    }

    /** @return list<float> */
    public function pendingDelays(): array
    {
        return array_values($this->pendingDelays);
    }
}
