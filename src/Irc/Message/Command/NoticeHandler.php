<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Protocol\Message;

final readonly class NoticeHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private MessageDelivery $delivery,
    ) {}

    public function command(): string
    {
        return 'NOTICE';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if (! $context->client->registration->isComplete()) {
            return;
        }

        if ($message->isParameterMissing(0) || $message->isParameterMissing(1)) {
            return;
        }

        $this->delivery->deliver(
            sender: $context->client,
            command: $this->command(),
            targets: $message->parameter(0),
            text: $message->parameter(1),
        );
    }
}
