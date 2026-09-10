<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Protocol\Message;

final readonly class AwayHandler implements CommandHandler
{
    public function __construct(
        private ServerLimits $limits,
        private AwayResponseFactory $awayResponses,
    ) {}

    public function command(): string
    {
        return 'AWAY';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->client->markPresent();
            $context->connection->send(
                $this->awayResponses->createNoLongerAwayResponse($context->responseTarget()),
            );

            return;
        }

        $context->client->markAway(
            $this->limits->truncateAwayMessage($message->parameter(0)),
        );
        $context->connection->send(
            $this->awayResponses->createMarkedAwayResponse($context->responseTarget()),
        );
    }
}
