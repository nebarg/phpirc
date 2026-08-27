<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;
use PhpIrc\Irc\Transport\Connection;

final readonly class PingHandler implements CommandHandler
{
    public function __construct(
        private ServerName $serverName,
    ) {}

    public function command(): string
    {
        return 'PING';
    }

    public function handle(Connection $connection, Message $message): void
    {
        if ($message->parameters === [] || $message->parameters[0] === '') {
            $response = new Message(
                [],
                $this->serverName->value,
                ResponseCode::NoOrigin->value,
                [
                    '*',
                    'No origin specified',
                ],
            );
        } else {
            $response = new Message(
                [],
                $this->serverName->value,
                'PONG',
                [
                    $this->serverName->value,
                    $message->parameters[0],
                ],
            );
        }

        $connection->send($response);
    }
}
