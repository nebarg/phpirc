<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport\Task;

use Closure;
use LogicException;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use SplQueue;

final class ManualBackgroundTaskRunner implements BackgroundTaskRunner
{
    /** @var SplQueue<Closure(): void> */
    private readonly SplQueue $tasks;

    public function __construct()
    {
        $this->tasks = new SplQueue();
    }

    public function run(Closure $task): void
    {
        $this->tasks->enqueue($task);
    }

    public function runNext(): void
    {
        if ($this->tasks->isEmpty()) {
            throw new LogicException('There are no background tasks to run.');
        }

        $this->tasks->dequeue()();
    }

    public function pendingCount(): int
    {
        return $this->tasks->count();
    }
}
