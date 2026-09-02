<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
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
        private ChannelBroadcaster $broadcaster,
    ) {}

    public function command(): string
    {
        return 'NICK';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissing(0)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoNicknameGiven,
                    target: $context->responseTarget(),
                ),
            );

            return;
        }

        $nickname = $message->parameter(0);

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
            $nicknameChanged = new Message(
                command: 'NICK',
                parameters: [$nickname],
                source: $oldNickname,
            );

            $context->connection->send($nicknameChanged);
            $this->broadcaster->broadcastToSharedChannelPeers(
                $context->client,
                $nicknameChanged,
            );

            return;
        }

        $this->registration->completeIfReady($context);
    }
}
