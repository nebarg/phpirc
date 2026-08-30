<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\CaseMapper;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class ChannelRegistryInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): ChannelRegistry
    {
        return new ChannelRegistry($container->get(CaseMapper::class));
    }
}
