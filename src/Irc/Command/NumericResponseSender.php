<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class NumericResponseSender
{
    public function __construct(
        private ServerName $serverName,
    ) {}

    /** @param list<string> $parameters */
    public function send(
        CommandContext $context,
        ResponseCode $code,
        array $parameters = [],
    ): void {
        $context->connection->send(
            new Message(
                tags: [],
                source: $this->serverName->value,
                command: $code->value,
                parameters: [
                    $context->client->nickname ?? '*',
                    ...$parameters,
                ],
            ),
        );
    }
}
