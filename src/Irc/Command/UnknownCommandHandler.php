<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Transport\Connection;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class UnknownCommandHandler implements CommandHandler
{
    public function __construct(private ServerName $serverName) {}

    public function command(): string
    {
        return 'unknown';
    }

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
                ]
            )
        );
    }
}
