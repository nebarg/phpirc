<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class RegistrationCompleter
{
    public function __construct(
        private ServerConfig $config,
        private NumericResponseFactory $responses,
    ) {}

    public function completeIfReady(CommandContext $context): void
    {
        $nickname = $context->client->nickname;

        if ($nickname === null || ! $context->client->completeRegistrationIfReady()) {
            return;
        }

        $context->connection->send(
            $this->responses->create(
                code: ResponseCode::Welcome,
                target: $nickname,
                text: "Welcome to the {$this->config->networkName} Network, {$nickname}",
            ),
        );
    }
}
