<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Client\ClientRegistry;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ClientRegistryInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ClientRegistry
    {
        return new ClientRegistry();
    }
}
