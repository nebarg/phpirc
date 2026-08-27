<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Command\CommandHandler;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

final class CommandHandlerDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly CommandHandlerRegistry $registry,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! str_starts_with($class->getName(), 'PhpIrc\\Irc\\Command\\')) {
            return;
        }

        if (! $class->implements(CommandHandler::class)) {
            return;
        }

        $this->discoveryItems->add($location, $class->getName());
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $handler) {
            /** @var class-string<CommandHandler> $handler */
            $this->registry->add($handler);
        }
    }
}
