<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class UserModeHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private NumericResponseFactory $numericResponses,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $nickname = $message->parameter(0);
        $client = $this->clients->findByNickname($nickname);

        if ($client === null || ! $client->registration->isComplete()) {
            $context->connection->send(
                $this->errors->noSuchNickname($context->responseTarget(), $nickname),
            );

            return;
        }

        if ($client !== $context->client) {
            $context->connection->send(
                $this->errors->usersDontMatch($context->responseTarget()),
            );

            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $context->connection->send(
                $this->numericResponses->create(
                    code: ResponseCode::UserModeIs,
                    target: $context->responseTarget(),
                    parameters: ['+'],
                ),
            );

            return;
        }

        if (trim($message->parameter(1), '+-') !== '') {
            $context->connection->send(
                $this->errors->unknownUserModeFlag($context->responseTarget()),
            );
        }
    }
}
