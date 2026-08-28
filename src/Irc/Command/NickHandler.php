<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Network\ClientRegistry;
use PhpIrc\Irc\Network\NicknameValidator;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class NickHandler implements CommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private NicknameValidator $nicknames,
        private NumericResponseSender $responses,
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
            $this->responses->send(
                $context,
                ResponseCode::NoNicknameGiven,
                ['No nickname given'],
            );

            return;
        }

        if (! $this->nicknames->isValid($nickname)) {
            $this->responses->send(
                $context,
                ResponseCode::ErroneousNickname,
                [$nickname, 'Erroneous nickname'],
            );

            return;
        }

        $oldNickname = $context->client->nickname;
        $wasRegistered = $context->client->registration->isComplete();

        if (! $this->clients->claimNickname($context->client, $nickname)) {
            $this->responses->send(
                $context,
                ResponseCode::NicknameInUse,
                [$nickname, 'Nickname is already in use'],
            );

            return;
        }

        if ($wasRegistered) {
            $context->connection->send(
                new Message(
                    tags: [],
                    source: $oldNickname,
                    command: 'NICK',
                    parameters: [$nickname],
                ),
            );

            return;
        }

        $this->registration->completeIfReady($context);
    }
}
