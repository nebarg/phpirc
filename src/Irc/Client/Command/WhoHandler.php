<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Policy\ClientVisibilityPolicy;
use PhpIrc\Irc\Client\Response\WhoResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class WhoHandler implements CommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ClientVisibilityPolicy $visibility,
        private WhoResponseFactory $whoResponses,
    ) {}

    public function command(): string
    {
        return 'WHO';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingMaskResponse($context);
            return;
        }

        $mask = $message->parameter(0);

        $this->sendMatches($context, $mask);
        $this->sendEndOfWhoResponse($context, $mask);
    }

    private function sendMissingMaskResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->whoResponses->createMissingMaskResponse($context->responseTarget()),
        );
    }

    private function sendMatches(CommandContext $context, string $mask): void
    {
        $channel = $this->channels->find($mask);

        if ($channel !== null) {
            $this->sendChannelMatches($context, $channel);
            return;
        }

        $this->sendNicknameMatch($context, $mask);
    }

    private function sendChannelMatches(CommandContext $context, Channel $channel): void
    {
        foreach ($channel->members() as $membership) {
            if (! $this->visibility->canSee($context->client, $membership->client)) {
                continue;
            }

            $context->connection->send(
                $this->whoResponses->createChannelMemberReply(
                    target: $context->responseTarget(),
                    channel: $channel,
                    membership: $membership,
                ),
            );
        }
    }

    private function sendNicknameMatch(CommandContext $context, string $nickname): void
    {
        $client = $this->clients->findByNickname($nickname);

        // @mago-format-ignore-next
        if (
            $client === null
            || ! $client->registration->isComplete()
            || ! $this->visibility->canSee($context->client, $client)
        ) {
            return;
        }

        $context->connection->send(
            $this->whoResponses->createClientReply(
                target: $context->responseTarget(),
                client: $client,
            ),
        );
    }

    private function sendEndOfWhoResponse(CommandContext $context, string $mask): void
    {
        $context->connection->send(
            $this->whoResponses->createEndOfWhoResponse(
                target: $context->responseTarget(),
                mask: $mask,
            ),
        );
    }
}
