<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Network\ClientRegistry;
use PhpIrc\Irc\Network\NicknameValidator;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

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
                    target: $context->client->nickname,
                ),
            );

            return;
        }

        if (! $this->nicknames->isValid($nickname)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::ErroneousNickname,
                    target: $context->client->nickname,
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
                    target: $context->client->nickname,
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
