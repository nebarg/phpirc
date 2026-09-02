<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\NicknameValidator;
use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class NickHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private NicknameValidator $nicknames,
        private NumericResponseFactory $responses,
        private RegistrationCompleter $registration,
    ) {}

    public function command(): string
    {
        return 'NICK';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $nickname = $message->parameters[0] ?? '';

        if ($nickname === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoNicknameGiven,
                    target: $context->responseTarget(),
                ),
            );

            return;
        }

        if (! $this->nicknames->isValid($nickname)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::ErroneousNickname,
                    target: $context->responseTarget(),
                    parameters: [$nickname],
                ),
            );

            return;
        }

        $oldNickname = $context->client->nickname;
        $wasRegistered = $context->client->registration->isComplete();

        if (! $this->clients->claimNickname($context->client, $nickname)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NicknameInUse,
                    target: $context->responseTarget(),
                    parameters: [$nickname],
                ),
            );

            return;
        }

        if ($wasRegistered) {
            $context->connection->send(
                new Message(
                    command: 'NICK',
                    parameters: [$nickname],
                    source: $oldNickname,
                ),
            );

            return;
        }

        $this->registration->completeIfReady($context);
    }
}
