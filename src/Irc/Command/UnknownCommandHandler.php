<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class UnknownCommandHandler implements MessageHandler
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $context->connection->send(
            $this->responses->create(
                code: ResponseCode::UnknownCommand,
                target: $context->client->nickname,
                parameters: [$message->command],
            ),
        );
    }
}
