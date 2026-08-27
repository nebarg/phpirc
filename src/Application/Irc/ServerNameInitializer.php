<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ServerNameInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ServerName
    {
        return $container->get(ServerConfig::class)->serverName;
    }
}
