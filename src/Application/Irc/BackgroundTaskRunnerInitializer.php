<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\Amp\AmpBackgroundTaskRunner;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class BackgroundTaskRunnerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): BackgroundTaskRunner
    {
        return $container->get(AmpBackgroundTaskRunner::class);
    }
}
