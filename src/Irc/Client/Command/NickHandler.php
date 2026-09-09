<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Channel\SharedChannelPeerBroadcaster;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\NicknameValidator;
use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class NickHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private NicknameValidator $nicknames,
        private NumericErrorResponseFactory $errors,
        private RegistrationCompleter $registration,
        private SharedChannelPeerBroadcaster $peers,
    ) {}

    public function command(): string
    {
        return 'NICK';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingNicknameResponse($context);
            return;
        }

        $nickname = $message->parameter(0);

        if (! $this->nicknames->isValid($nickname)) {
            $this->sendInvalidNicknameResponse($context, $nickname);
            return;
        }

        $previousNickname = $context->client->nickname;
        $wasRegistered = $context->client->registration->isComplete();

        if (! $this->clients->claimNickname($context->client, $nickname)) {
            $this->sendNicknameInUseResponse($context, $nickname);
            return;
        }

        if ($wasRegistered) {
            $this->announceNicknameChange($context, $nickname, $previousNickname);
            return;
        }

        $this->registration->completeIfReady($context);
    }

    private function sendMissingNicknameResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->errors->noNicknameGiven($context->responseTarget()),
        );
    }

    private function sendInvalidNicknameResponse(CommandContext $context, string $nickname): void
    {
        $context->connection->send(
            $this->errors->erroneousNickname($context->responseTarget(), $nickname),
        );
    }

    private function sendNicknameInUseResponse(CommandContext $context, string $nickname): void
    {
        $context->connection->send(
            $this->errors->nicknameInUse($context->responseTarget(), $nickname),
        );
    }

    private function announceNicknameChange(
        CommandContext $context,
        string $nickname,
        ?string $previousNickname,
    ): void {
        $nicknameChanged = new Message(
            command: $this->command(),
            parameters: [$nickname],
            source: $previousNickname,
        );

        $context->connection->send($nicknameChanged);
        $this->peers->broadcast($context->client, $nicknameChanged);
    }
}
