<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\KeepaliveConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class KeepaliveConfigTest extends TestCase
{
    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $config = new KeepaliveConfig();

        $this->assertSame(120, $config->pingIntervalSeconds);
        $this->assertSame(30, $config->pongTimeoutSeconds);
    }

    #[Test]
    public function it_accepts_positive_timeouts(): void
    {
        $config = new KeepaliveConfig(
            pingIntervalSeconds: 60,
            pongTimeoutSeconds: 15,
        );

        $this->assertSame(60, $config->pingIntervalSeconds);
        $this->assertSame(15, $config->pongTimeoutSeconds);
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function invalidTimeouts(): iterable
    {
        yield 'zero ping interval' => [0, 30, 'Ping interval must be at least one second.'];
        yield 'negative ping interval' => [-1, 30, 'Ping interval must be at least one second.'];
        yield 'zero PONG timeout' => [120, 0, 'PONG timeout must be at least one second.'];
        yield 'negative PONG timeout' => [120, -1, 'PONG timeout must be at least one second.'];
    }

    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function it_rejects_non_positive_timeouts(
        int $pingIntervalSeconds,
        int $pongTimeoutSeconds,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new KeepaliveConfig($pingIntervalSeconds, $pongTimeoutSeconds);
    }
}
