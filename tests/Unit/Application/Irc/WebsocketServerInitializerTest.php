<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Irc;

use LogicException;
use PhpIrc\Application\Irc\WebsocketServerInitializer;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Config\WebsocketConfig;
use PhpIrc\Irc\Transport\Amp\Websocket\AmpIrcWebsocketServer;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\Websocket\DisabledWebsocketServer;
use PhpIrc\Irc\Transport\Websocket\WebsocketServer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tempest\Container\Container;
use Tests\TestCase;

final class WebsocketServerInitializerTest extends TestCase
{
    #[Test]
    public function it_disables_websockets_when_they_are_not_configured(): void
    {
        $container = $this->createMock(Container::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(ServerConfig::class)
            ->willReturn($this->serverConfig());

        $server = new WebsocketServerInitializer()->initialize($container);

        $this->assertInstanceOf(DisabledWebsocketServer::class, $server);
    }

    #[Test]
    public function it_builds_the_amp_server_when_websockets_are_configured(): void
    {
        $config = $this->serverConfig(new WebsocketConfig(
            '127.0.0.1',
            8081,
            '/irc',
            ['https://app.example.com'],
        ));
        $connections = $this->createStub(ClientConnectionSupervisor::class);
        $logger = $this->createStub(LoggerInterface::class);
        $container = $this->createStub(Container::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn (string $class): object => match ($class) {
                    ServerConfig::class => $config,
                    ClientConnectionSupervisor::class => $connections,
                    LoggerInterface::class => $logger,
                    default => throw new LogicException("Unexpected container request for {$class}."),
                },
            );

        $server = new WebsocketServerInitializer()->initialize($container);

        $this->assertInstanceOf(AmpIrcWebsocketServer::class, $server);
        $this->assertInstanceOf(WebsocketServer::class, $server);
    }

    private function serverConfig(?WebsocketConfig $websocket = null): ServerConfig
    {
        return new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [],
            websocket: $websocket,
        );
    }
}
