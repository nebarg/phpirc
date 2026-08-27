<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use LogicException;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Amp\AmpClientListener;
use PhpIrc\Irc\Transport\ClientListener;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

use function Amp\Socket\listen;

final readonly class ClientListenerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ClientListener
    {
        $config = $container->get(ServerConfig::class);

        if (count($config->listeners) !== 1) {
            throw new LogicException('Exactly one listener is currently supported.');
        }

        return new AmpClientListener(
            listen($config->listeners[0]->address()),
        );
    }
}
