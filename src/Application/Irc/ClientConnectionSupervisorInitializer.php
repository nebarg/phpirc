<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\Amp\AmpClientConnectionSupervisor;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ClientConnectionSupervisorInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ClientConnectionSupervisor
    {
        return $container->get(AmpClientConnectionSupervisor::class);
    }
}
