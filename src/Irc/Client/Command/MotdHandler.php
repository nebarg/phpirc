<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\MotdResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class MotdHandler implements CommandHandler
{
    public function __construct(
        private MotdResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'MOTD';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $context->connection->sendMany(
            $this->responses->createMotdResponses($context->responseTarget()),
        );
    }
}
