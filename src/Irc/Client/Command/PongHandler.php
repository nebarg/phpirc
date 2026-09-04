<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class PongHandler implements PreRegistrationCommandHandler
{
    public function command(): string
    {
        return 'PONG';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            return;
        }

        $context->connection->pongReceived(
            $message->parameter(0),
        );
    }
}
