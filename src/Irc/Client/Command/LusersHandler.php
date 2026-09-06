<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\LusersResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class LusersHandler implements CommandHandler
{
    public function __construct(
        private LusersResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'LUSERS';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        array_map(
            $context->connection->send(...),
            $this->responses->createResponses($context->responseTarget()),
        );
    }
}
