<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\FloodProtectionConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FloodProtectionConfigTest extends TestCase
{
    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $config = new FloodProtectionConfig();

        $this->assertSame(20, $config->burstMessages);
        $this->assertSame(2, $config->messagesPerSecond);
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero burst' => [0, 2, 'Message burst must be at least one.'];
        yield 'negative burst' => [-1, 2, 'Message burst must be at least one.'];
        yield 'zero rate' => [20, 0, 'Message rate must be at least one per second.'];
        yield 'negative rate' => [20, -1, 'Message rate must be at least one per second.'];
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_rejects_non_positive_limits(
        int $burstMessages,
        int $messagesPerSecond,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new FloodProtectionConfig($burstMessages, $messagesPerSecond);
    }
}
