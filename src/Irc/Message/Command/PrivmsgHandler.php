<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class PrivmsgHandler implements CommandHandler
{
    public function __construct(
        private MessageDelivery $delivery,
        private NumericResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'PRIVMSG';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissing(0)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoRecipient,
                    target: $context->responseTarget(),
                ),
            );

            return;
        }

        $targets = $message->parameter(0);

        if ($message->isParameterMissing(1)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoTextToSend,
                    target: $context->responseTarget(),
                ),
            );

            return;
        }

        $unresolvedTargets = $this->delivery->deliver(
            sender: $context->client,
            command: $this->command(),
            targets: $targets,
            text: $message->parameter(1),
        );

        foreach ($unresolvedTargets as $target) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoSuchNick,
                    target: $context->responseTarget(),
                    parameters: [$target === '' ? '*' : $target],
                ),
            );
        }
    }
}
