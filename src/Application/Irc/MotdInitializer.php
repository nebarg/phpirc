<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Client\MotdFileLoader;
use PhpIrc\Irc\Config\ServerConfig;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class MotdInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): Motd
    {
        return $container
            ->get(MotdFileLoader::class)
            ->load($container->get(ServerConfig::class)->motdFile);
    }
}
