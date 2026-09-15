<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\WebsocketConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WebsocketConfigTest extends TestCase
{
    #[Test]
    public function it_exposes_its_listener_and_security_settings(): void
    {
        $config = new WebsocketConfig(
            address: '127.0.0.1',
            port: 8081,
            path: '/irc',
            allowedOrigins: ['https://chat.example.com'],
            trustedProxies: ['127.0.0.1'],
        );

        $this->assertSame('127.0.0.1:8081', $config->address());
        $this->assertSame('/irc', $config->path);
        $this->assertSame(['https://chat.example.com'], $config->allowedOrigins);
        $this->assertSame(['127.0.0.1'], $config->trustedProxies);
    }

    /** @return iterable<string, array{string, int, string, list<string>, list<string>, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'empty address' => ['', 8081, '/irc', ['*'], [], 'WebSocket address cannot be empty.'];
        yield 'port below range' => ['127.0.0.1', 0, '/irc', ['*'], [], 'WebSocket port must be between 1 and 65535.'];
        yield 'port above range' => ['127.0.0.1', 65_536, '/irc', ['*'], [], 'WebSocket port must be between 1 and 65535.'];
        yield 'relative path' => [
            '127.0.0.1',
            8081,
            'irc',
            ['*'],
            [],
            'WebSocket path must begin with / and cannot contain a query or fragment.',
        ];
        yield 'path with query' => [
            '127.0.0.1',
            8081,
            '/irc?token=x',
            ['*'],
            [],
            'WebSocket path must begin with / and cannot contain a query or fragment.',
        ];
        yield 'path with fragment' => [
            '127.0.0.1',
            8081,
            '/irc#chat',
            ['*'],
            [],
            'WebSocket path must begin with / and cannot contain a query or fragment.',
        ];
        yield 'no origins' => ['127.0.0.1', 8081, '/irc', [], [], 'At least one WebSocket origin must be allowed.'];
        yield 'empty origin' => ['127.0.0.1', 8081, '/irc', [''], [], 'Allowed WebSocket origins cannot be empty.'];
        yield 'empty proxy' => ['127.0.0.1', 8081, '/irc', ['*'], [''], 'Trusted WebSocket proxies cannot be empty.'];
    }

    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $trustedProxies
     */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function it_rejects_invalid_settings(
        string $address,
        int $port,
        string $path,
        array $allowedOrigins,
        array $trustedProxies,
        string $expectedMessage,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new WebsocketConfig($address, $port, $path, $allowedOrigins, $trustedProxies);
    }
}
