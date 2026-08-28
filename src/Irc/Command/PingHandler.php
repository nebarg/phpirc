<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class PingHandler implements CommandHandler
{
    public function __construct(
        private ServerName $serverName,
        private NumericResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'PING';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->parameters === [] || $message->parameters[0] === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoOrigin,
                    target: $context->client->nickname,
                ),
            );

            return;
        }

        $response = new Message(
            command: 'PONG',
            parameters: [
                $this->serverName->value,
                $message->parameters[0],
            ],
            source: $this->serverName->value,
        );

        $context->connection->send($response);
    }
}
