<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Message\PrivmsgResponseFactory;
use PhpIrc\Irc\Protocol\Message;

final readonly class PrivmsgHandler implements CommandHandler
{
    public function __construct(
        private MessageDelivery $delivery,
        private PrivmsgResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'PRIVMSG';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->responses->createMissingRecipientResponse($context->responseTarget()),
            );

            return;
        }

        $targets = $message->parameter(0);

        if ($message->isParameterMissingOrEmpty(1)) {
            $context->connection->send(
                $this->responses->createMissingTextResponse($context->responseTarget()),
            );

            return;
        }

        $failures = $this->delivery->deliver(
            sender: $context->client,
            command: $this->command(),
            targets: $targets,
            text: $message->parameter(1),
        );

        foreach ($failures as $failure) {
            $context->connection->send(
                $this->responses->createDeliveryFailureResponse($context->responseTarget(), $failure),
            );
        }
    }
}
