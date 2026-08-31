<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\ClientConnectionRegistry;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ClientConnectionRegistryInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ClientConnectionRegistry
    {
        return new ClientConnectionRegistry();
    }
}
