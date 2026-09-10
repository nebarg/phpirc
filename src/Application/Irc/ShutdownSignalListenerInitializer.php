<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Transport\Revolt\RevoltShutdownSignalListener;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ShutdownSignalListenerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ShutdownSignalListener
    {
        return $container->get(RevoltShutdownSignalListener::class);
    }
}
