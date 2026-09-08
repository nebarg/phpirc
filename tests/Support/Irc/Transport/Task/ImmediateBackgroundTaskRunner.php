<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport\Task;

use Closure;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;

final class ImmediateBackgroundTaskRunner implements BackgroundTaskRunner
{
    public function run(Closure $task): void
    {
        $task();
    }
}
