<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Revolt;

use PhpIrc\Irc\Transport\Revolt\RevoltTimerScheduler;
use PHPUnit\Framework\Attributes\Test;
use Revolt\EventLoop;
use Tests\TestCase;

final class RevoltTimerSchedulerTest extends TestCase
{
    #[Test]
    public function it_runs_a_delayed_callback(): void
    {
        $called = false;
        $scheduler = new RevoltTimerScheduler();

        $scheduler->delay(0.0, function () use (&$called): void {
            $called = true;
        });

        EventLoop::run();

        $this->assertTrue($called);
    }

    #[Test]
    public function it_cancels_a_delayed_callback(): void
    {
        $called = false;
        $scheduler = new RevoltTimerScheduler();
        $timerId = $scheduler->delay(0.0, function () use (&$called): void {
            $called = true;
        });

        $scheduler->cancel($timerId);
        EventLoop::run();

        $this->assertFalse($called);
    }
}
