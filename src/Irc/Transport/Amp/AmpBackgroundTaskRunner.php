<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Closure;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use Psr\Log\LoggerInterface;
use Throwable;

use function Amp\async;

final readonly class AmpBackgroundTaskRunner implements BackgroundTaskRunner
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function run(Closure $task): void
    {
        async($task)
            ->catch(function (Throwable $exception): void {
                $this->logger->error(
                    'IRC background task failed.',
                    ['exception' => $exception],
                );
            })
            ->ignore();
    }
}
