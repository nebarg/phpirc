<?php

declare(strict_types=1);

namespace PhpIrc\Application\Irc;

use PhpIrc\Irc\Command\CommandHandler;
use Tempest\Container\Singleton;

#[Singleton]
final class CommandHandlerRegistry
{
    /** @var array<class-string<CommandHandler>, class-string<CommandHandler>> */
    private array $handlers = [];

    /** @param class-string<CommandHandler> $handler */
    public function add(string $handler): void
    {
        $this->handlers[$handler] = $handler;
    }

    /** @return list<class-string<CommandHandler>> */
    public function all(): array
    {
        return array_values($this->handlers);
    }
}
