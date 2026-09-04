<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Flood;

use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Transport\Flood\MessageRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\Time\ManualMonotonicClock;
use Tests\TestCase;

final class MessageRateLimiterTest extends TestCase
{
    #[Test]
    public function it_accepts_messages_within_the_initial_burst(): void
    {
        $limiter = $this->limiter(new ManualMonotonicClock());

        $this->assertTrue($limiter->accept());
        $this->assertTrue($limiter->accept());
        $this->assertFalse($limiter->accept());
    }

    #[Test]
    public function it_refills_capacity_over_time(): void
    {
        $clock = new ManualMonotonicClock();
        $limiter = $this->limiter($clock);
        $limiter->accept();
        $limiter->accept();

        $clock->advance(0.5);

        $this->assertTrue($limiter->accept());
        $this->assertFalse($limiter->accept());
    }

    #[Test]
    public function it_does_not_refill_beyond_the_burst_capacity(): void
    {
        $clock = new ManualMonotonicClock();
        $limiter = $this->limiter($clock);
        $limiter->accept();
        $limiter->accept();

        $clock->advance(10);

        $this->assertTrue($limiter->accept());
        $this->assertTrue($limiter->accept());
        $this->assertFalse($limiter->accept());
    }

    private function limiter(ManualMonotonicClock $clock): MessageRateLimiter
    {
        return new MessageRateLimiter(
            clock: $clock,
            config: new FloodProtectionConfig(
                burstMessages: 2,
                messagesPerSecond: 2,
            ),
        );
    }
}
