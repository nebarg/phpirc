<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\Revolt\RevoltTimerScheduler;
use PhpIrc\Irc\Transport\Timer\TimerScheduler;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class TimerSchedulerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): TimerScheduler
    {
        return $container->get(RevoltTimerScheduler::class);
    }
}
