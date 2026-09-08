<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Task;

use Closure;

interface BackgroundTaskRunner
{
    /** @param Closure(): void $task */
    public function run(Closure $task): void;
}
