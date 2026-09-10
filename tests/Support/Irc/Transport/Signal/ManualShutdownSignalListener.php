<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport\Signal;

use Closure;
use LogicException;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;

final class ManualShutdownSignalListener implements ShutdownSignalListener
{
    private ?Closure $requestShutdown = null;

    public int $startCalls = 0;

    public int $stopCalls = 0;

    public function start(Closure $requestShutdown): void
    {
        $this->startCalls++;
        $this->requestShutdown = $requestShutdown;
    }

    public function stop(): void
    {
        $this->stopCalls++;
        $this->requestShutdown = null;
    }

    public function requestShutdown(): void
    {
        if ($this->requestShutdown === null) {
            throw new LogicException('The shutdown signal listener has not been started.');
        }

        ($this->requestShutdown)();
    }
}
