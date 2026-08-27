<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Irc;

use LogicException;
use PhpIrc\Application\Irc\ClientListenerInitializer;
use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Container\Container;
use Tests\TestCase;

final class ClientListenerInitializerTest extends TestCase
{
    /** @return iterable<string, array{list<ListenerConfig>}> */
    public static function unsupportedListenerSets(): iterable
    {
        yield 'no listeners' => [[]];
        yield 'multiple listeners' => [[
            new ListenerConfig('127.0.0.1', 6667),
            new ListenerConfig('127.0.0.1', 6697),
        ]];
    }

    /** @param list<ListenerConfig> $listeners */
    #[Test]
    #[DataProvider('unsupportedListenerSets')]
    public function it_requires_exactly_one_listener(array $listeners): void
    {
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: $listeners,
        );
        $container = $this->createMock(Container::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(ServerConfig::class)
            ->willReturn($config);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exactly one listener is currently supported.');

        new ClientListenerInitializer()->initialize($container);
    }
}
