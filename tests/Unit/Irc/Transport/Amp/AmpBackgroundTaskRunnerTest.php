<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use PhpIrc\Irc\Transport\Amp\AmpBackgroundTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;
use Tests\TestCase;

final class AmpBackgroundTaskRunnerTest extends TestCase
{
    #[Test]
    public function it_runs_a_task_without_blocking_the_caller(): void
    {
        $events = [];
        $runner = new AmpBackgroundTaskRunner(new NullLogger());

        $runner->run(static function () use (&$events): void {
            $events[] = 'task';
        });

        $events[] = 'caller';

        EventLoop::run();

        $this->assertSame(['caller', 'task'], $events);
    }

    #[Test]
    public function it_logs_an_uncaught_background_task_failure(): void
    {
        $failure = new RuntimeException('Task failed.');
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'IRC background task failed.',
                ['exception' => $failure],
            );
        $runner = new AmpBackgroundTaskRunner($logger);

        $runner->run(static function () use ($failure): void {
            throw $failure;
        });

        EventLoop::run();
    }
}
