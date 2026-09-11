<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use LogicException;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Amp\AmpClientListenerFactory;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ClientListenerInitializer implements Initializer
{
    public function __construct(
        private AmpClientListenerFactory $listeners = new AmpClientListenerFactory(),
    ) {}

    #[Singleton]
    public function initialize(Container $container): ClientListenerCollection
    {
        $config = $container->get(ServerConfig::class);

        if ($config->listeners === []) {
            throw new LogicException('At least one listener must be configured.');
        }

        $listeners = [];

        try {
            foreach ($config->listeners as $listener) {
                $listeners[] = $this->listeners->create($listener);
            }
        } catch (\Throwable $exception) {
            foreach ($listeners as $listener) {
                $listener->close();
            }

            throw $exception;
        }

        return new ClientListenerCollection($listeners);
    }
}
