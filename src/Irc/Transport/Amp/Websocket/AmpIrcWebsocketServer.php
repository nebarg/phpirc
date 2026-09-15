<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp\Websocket;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware\ForwardedHeaderType;
use Amp\Http\Server\SocketHttpServer;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use Amp\Websocket\Server\Rfc6455ClientFactory;
use Amp\Websocket\Server\Websocket;
use PhpIrc\Irc\Config\WebsocketConfig;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\Websocket\WebsocketServer;
use Psr\Log\LoggerInterface;

final class AmpIrcWebsocketServer implements WebsocketServer
{
    private bool $running = false;

    private ?HttpServer $httpServer;

    public function __construct(
        private readonly WebsocketConfig $config,
        private readonly ClientConnectionSupervisor $connections,
        private readonly LoggerInterface $logger,
        ?HttpServer $httpServer = null,
    ) {
        $this->httpServer = $httpServer;
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $errors = new DefaultErrorHandler();
        $this->httpServer ??= $this->createHttpServer();
        $server = $this->httpServer;
        $websocket = new Websocket(
            httpServer: $server,
            logger: $this->logger,
            acceptor: new IrcWebsocketAcceptor($this->config, $errors),
            clientHandler: new IrcWebsocketClientHandler($this->connections),
            clientFactory: new Rfc6455ClientFactory(
                heartbeatQueue: null,
                rateLimit: null,
                parserFactory: new Rfc6455ParserFactory(
                    messageSizeLimit: AmpWebsocketClientSocket::MAX_PAYLOAD_BYTES,
                    frameSizeLimit: AmpWebsocketClientSocket::MAX_PAYLOAD_BYTES,
                ),
            ),
        );

        $server->start($websocket, $errors);
        $this->running = true;
    }

    public function stop(): void
    {
        if (! $this->running) {
            return;
        }

        $this->running = false;
        $this->httpServer?->stop();
    }

    private function createHttpServer(): SocketHttpServer
    {
        $server = $this->config->trustedProxies === []
            ? SocketHttpServer::createForDirectAccess(
                logger: $this->logger,
                enableCompression: false,
                allowedMethods: ['GET'],
            )
            : SocketHttpServer::createForBehindProxy(
                logger: $this->logger,
                headerType: ForwardedHeaderType::XForwardedFor,
                trustedProxies: $this->config->trustedProxies,
                enableCompression: false,
                allowedMethods: ['GET'],
            );

        $server->expose($this->config->address());

        return $server;
    }
}
