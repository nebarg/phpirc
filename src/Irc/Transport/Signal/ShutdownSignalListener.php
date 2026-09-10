<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Signal;

use Closure;

interface ShutdownSignalListener
{
    /** @param Closure(): void $requestShutdown */
    public function start(Closure $requestShutdown): void;

    public function stop(): void;
}
