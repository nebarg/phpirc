<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use LogicException;
use PhpIrc\Irc\Protocol\Message;

final readonly class CommandDispatcher implements MessageHandler
{
    /** @var array<string, CommandHandler> */
    private array $handlers;

    /**
     * @param iterable<CommandHandler> $handlers
     * @return void
     */
    public function __construct(
        iterable $handlers,
        private MessageHandler $unknownCommand,
    ) {
        $index = [];

        foreach ($handlers as $handler) {
            $command = strtoupper($handler->command());

            if (isset($index[$command])) {
                throw new LogicException("Duplicate {$command} handler found.");
            }

            $index[$command] = $handler;
        }

        $this->handlers = $index;
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $command = $this->handlers[strtoupper($message->command)] ?? null;

        $command === null
            ? $this->unknownCommand->handle($context, $message)
            : $command->handle($context, $message);
    }
}
