<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp\Websocket;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Websocket\Server\Websocket;
use PhpIrc\Irc\Config\WebsocketConfig;
use PhpIrc\Irc\Transport\Amp\Websocket\AmpIrcWebsocketServer;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class AmpIrcWebsocketServerTest extends TestCase
{
    #[Test]
    public function it_starts_and_stops_the_http_server_once(): void
    {
        $httpServer = $this->createMock(HttpServer::class);
        $httpServer->expects($this->once())->method('onStop');
        $httpServer
            ->expects($this->once())
            ->method('start')
            ->with(
                $this->isInstanceOf(Websocket::class),
                $this->isInstanceOf(DefaultErrorHandler::class),
            );
        $httpServer->expects($this->once())->method('stop');
        $server = new AmpIrcWebsocketServer(
            config: new WebsocketConfig('127.0.0.1', 8081, '/irc', ['*']),
            connections: $this->createStub(ClientConnectionSupervisor::class),
            logger: $this->createStub(LoggerInterface::class),
            httpServer: $httpServer,
        );

        $server->start();
        $server->start();
        $server->stop();
        $server->stop();
    }
}
