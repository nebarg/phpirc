<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Revolt;

use Closure;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use Revolt\EventLoop;

final class RevoltShutdownSignalListener implements ShutdownSignalListener
{
    /** @var list<string> */
    private array $watcherIds = [];

    public function start(Closure $requestShutdown): void
    {
        $this->stop();

        $callback = static function (string $watcherId, int $signal) use ($requestShutdown): void {
            $requestShutdown();
        };

        $this->watcherIds = [
            EventLoop::onSignal(SIGINT, $callback),
            EventLoop::onSignal(SIGTERM, $callback),
        ];
    }

    public function stop(): void
    {
        foreach ($this->watcherIds as $watcherId) {
            EventLoop::cancel($watcherId);
        }

        $this->watcherIds = [];
    }
}
