<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\OutboundQueueConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OutboundQueueConfigTest extends TestCase
{
    #[Test]
    public function it_provides_a_default_queue_limit(): void
    {
        $this->assertSame(262_144, new OutboundQueueConfig()->maximumBytes);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_rejects_invalid_queue_limits(int $maximumBytes): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum outbound queue bytes must be at least one.');

        new OutboundQueueConfig($maximumBytes);
    }
}
