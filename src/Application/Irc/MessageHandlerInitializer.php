<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Command\Fallback\NotRegisteredHandler;
use PhpIrc\Irc\Command\Fallback\UnknownCommandHandler;
use PhpIrc\Irc\Command\MessageHandler;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final readonly class MessageHandlerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): MessageHandler
    {
        $registry = $container->get(CommandHandlerRegistry::class);
        $handlers = [];

        foreach ($registry->all() as $handler) {
            $handlers[] = $container->get($handler);
        }

        return new CommandDispatcher(
            handlers: $handlers,
            unknownCommand: $container->get(UnknownCommandHandler::class),
            notRegistered: $container->get(NotRegisteredHandler::class),
        );
    }
}
