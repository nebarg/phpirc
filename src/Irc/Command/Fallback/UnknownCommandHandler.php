<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command\Fallback;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class UnknownCommandHandler implements MessageHandler
{
    public function __construct(
        private NumericErrorResponseFactory $errors,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $context->connection->send(
            $this->errors->unknownCommand($context->responseTarget(), $message->command),
        );
    }
}
