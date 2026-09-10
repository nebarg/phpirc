<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Mode\UserModeChanger;
use PhpIrc\Irc\Client\Mode\UserModeChangeResult;
use PhpIrc\Irc\Client\Response\UserModeResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Protocol\Message;

final readonly class UserModeHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private UserModeChanger $modeChanger,
        private UserModeResponseFactory $modeResponses,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $nickname = $message->parameter(0);
        $client = $this->clients->findByNickname($nickname);

        if ($client === null || ! $client->registration->isComplete()) {
            $this->sendUnknownNicknameResponse($context, $nickname);
            return;
        }

        if ($client !== $context->client) {
            $this->sendOtherUserResponse($context);
            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendCurrentModes($context, $client);
            return;
        }

        $result = $this->applyChanges($client, $message);

        $this->sendUnknownModeResponse($context, $result);
        $this->sendAppliedChanges($context, $result);
    }

    private function sendUnknownNicknameResponse(CommandContext $context, string $nickname): void
    {
        $context->connection->send(
            $this->modeResponses->createUnknownNicknameResponse(
                $context->responseTarget(),
                $nickname,
            ),
        );
    }

    private function sendOtherUserResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->modeResponses->createOtherUserResponse($context->responseTarget()),
        );
    }

    private function sendCurrentModes(CommandContext $context, Client $client): void
    {
        $context->connection->send(
            $this->modeResponses->createCurrentModesResponse(
                $context->responseTarget(),
                $client,
            ),
        );
    }

    private function applyChanges(Client $client, Message $message): UserModeChangeResult
    {
        return $this->modeChanger->apply($client, $message->parameter(1));
    }

    private function sendUnknownModeResponse(CommandContext $context, UserModeChangeResult $result): void
    {
        if (! $result->hasUnknownModes) {
            return;
        }

        $context->connection->send(
            $this->modeResponses->createUnknownModeResponse($context->responseTarget()),
        );
    }

    private function sendAppliedChanges(CommandContext $context, UserModeChangeResult $result): void
    {
        if ($result->appliedChanges === []) {
            return;
        }

        $actorNickname = $context->actorNickname();

        $context->connection->send(
            $this->modeResponses->createChangedMessage(
                $actorNickname,
                $actorNickname,
                $result->appliedChanges,
            ),
        );
    }
}
