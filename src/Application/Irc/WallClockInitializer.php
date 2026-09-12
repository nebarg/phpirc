<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Time\SystemWallClock;
use PhpIrc\Irc\Time\WallClock;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class WallClockInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): WallClock
    {
        return $container->get(SystemWallClock::class);
    }
}
