<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Mode\UserModeChanger;
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
            $context->connection->send(
                $this->modeResponses->createUnknownNicknameResponse(
                    $context->responseTarget(),
                    $nickname,
                ),
            );

            return;
        }

        if ($client !== $context->client) {
            $context->connection->send(
                $this->modeResponses->createOtherUserResponse($context->responseTarget()),
            );

            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $context->connection->send(
                $this->modeResponses->createCurrentModesResponse(
                    $context->responseTarget(),
                    $client,
                ),
            );

            return;
        }

        $result = $this->modeChanger->apply($client, $message->parameter(1));

        if ($result->hasUnknownModes) {
            $context->connection->send(
                $this->modeResponses->createUnknownModeResponse($context->responseTarget()),
            );
        }

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
