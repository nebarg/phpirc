<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;
use PhpIrc\Irc\Transport\Connection;

final readonly class UnknownCommandHandler
{
    public function __construct(
        private ServerName $serverName,
    ) {}

    public function handle(Connection $connection, Message $message): void
    {
        $connection->send(
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
