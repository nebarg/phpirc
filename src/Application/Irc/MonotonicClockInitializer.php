<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\Time\MonotonicClock;
use PhpIrc\Irc\Transport\Time\SystemMonotonicClock;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class MonotonicClockInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): MonotonicClock
    {
        return $container->get(SystemMonotonicClock::class);
    }
}
