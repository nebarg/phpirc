<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Message\MessageDeliveryReport;
use PhpIrc\Irc\Message\Response\PrivmsgResponseFactory;
use PhpIrc\Irc\Protocol\Message;

final readonly class PrivmsgHandler implements CommandHandler
{
    public function __construct(
        private MessageDelivery $delivery,
        private PrivmsgResponseFactory $privmsgResponses,
    ) {}

    public function command(): string
    {
        return 'PRIVMSG';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingRecipientResponse($context);
            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendMissingTextResponse($context);
            return;
        }

        $report = $this->delivery->deliver(
            sender: $context->client,
            command: $this->command(),
            targets: $message->parameter(0),
            text: $message->parameter(1),
        );

        $this->sendDeliveryFailureResponses($context, $report);
        $this->sendAwayResponses($context, $report);
    }

    private function sendMissingRecipientResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->privmsgResponses->createMissingRecipientResponse($context->responseTarget()),
        );
    }

    private function sendMissingTextResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->privmsgResponses->createMissingTextResponse($context->responseTarget()),
        );
    }

    private function sendDeliveryFailureResponses(
        CommandContext $context,
        MessageDeliveryReport $report,
    ): void {
        foreach ($report->failures as $failure) {
            $context->connection->send(
                $this->privmsgResponses->createDeliveryFailureResponse($context->responseTarget(), $failure),
            );
        }
    }

    private function sendAwayResponses(CommandContext $context, MessageDeliveryReport $report): void
    {
        foreach ($report->awayRecipients as $recipient) {
            $context->connection->send(
                $this->privmsgResponses->createRecipientAwayResponse($context->responseTarget(), $recipient),
            );
        }
    }
}
