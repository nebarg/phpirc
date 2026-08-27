<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\ListenerConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class ListenerConfigTest extends IntegrationTestCase
{
    #[Test]
    public function it_combines_the_address_and_port(): void
    {
        $listener = new ListenerConfig('127.0.0.1', 6667);

        $this->assertSame('127.0.0.1:6667', $listener->address());
    }

    /** @return iterable<string, array{int}> */
    public static function validPorts(): iterable
    {
        yield 'lowest port' => [1];
        yield 'highest port' => [65_535];
    }

    #[Test]
    #[DataProvider('validPorts')]
    public function it_accepts_ports_at_the_supported_boundaries(int $port): void
    {
        $listener = new ListenerConfig('127.0.0.1', $port);

        $this->assertSame("127.0.0.1:{$port}", $listener->address());
    }

    #[Test]
    public function it_rejects_an_empty_address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Listener address cannot be empty.');

        new ListenerConfig('', 6667);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidPorts(): iterable
    {
        yield 'below the lowest port' => [0];
        yield 'above the highest port' => [65_536];
    }

    #[Test]
    #[DataProvider('invalidPorts')]
    public function it_rejects_ports_outside_the_supported_range(int $port): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Listener port must be between 1 and 65535.');

        new ListenerConfig('127.0.0.1', $port);
    }
}
