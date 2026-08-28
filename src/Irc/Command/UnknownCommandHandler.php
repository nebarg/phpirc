<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class UnknownCommandHandler implements MessageHandler
{
    public function __construct(
        private ServerName $serverName,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $context->connection->send(
            new Message(
                [],
                $this->serverName->value,
                ResponseCode::UnknownCommand->value,
                [
                    '*',
                    $message->command,
                    'Unknown command',
                ],
            ),
        );
    }
}
