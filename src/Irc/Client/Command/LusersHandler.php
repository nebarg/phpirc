<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Response\LusersResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class LusersHandler implements CommandHandler
{
    public function __construct(
        private LusersResponseFactory $lusersResponses,
    ) {}

    public function command(): string
    {
        return 'LUSERS';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $context->connection->sendMany(
            $this->lusersResponses->createLusersResponses($context->responseTarget()),
        );
    }
}
