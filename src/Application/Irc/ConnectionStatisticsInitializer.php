<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Transport\ConnectionStatistics;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ConnectionStatisticsInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ConnectionStatistics
    {
        return new ConnectionStatistics($container->get(ClientRegistry::class));
    }
}
