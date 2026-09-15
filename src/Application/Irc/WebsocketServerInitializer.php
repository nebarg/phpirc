<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Amp\Websocket\AmpIrcWebsocketServer;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\Websocket\DisabledWebsocketServer;
use PhpIrc\Irc\Transport\Websocket\WebsocketServer;
use Psr\Log\LoggerInterface;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class WebsocketServerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): WebsocketServer
    {
        $config = $container->get(ServerConfig::class)->websocket;

        if ($config === null) {
            return new DisabledWebsocketServer();
        }

        return new AmpIrcWebsocketServer(
            config: $config,
            connections: $container->get(ClientConnectionSupervisor::class),
            logger: $container->get(LoggerInterface::class),
        );
    }
}
