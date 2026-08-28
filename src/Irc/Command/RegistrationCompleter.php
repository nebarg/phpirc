<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class RegistrationCompleter
{
    public function __construct(
        private ServerConfig $config,
    ) {}

    public function completeIfReady(CommandContext $context): void
    {
        $nickname = $context->client->nickname;

        if ($nickname === null || ! $context->client->completeRegistrationIfReady()) {
            return;
        }

        $context->connection->send(
            new Message(
                tags: [],
                source: $this->config->serverName->value,
                command: ResponseCode::Welcome->value,
                parameters: [
                    $nickname,
                    "Welcome to the {$this->config->networkName} Network, {$nickname}",
                ],
            ),
        );
    }
}
