<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelNameValidator;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Response\ChannelNamesResponseFactory;
use PhpIrc\Irc\Channel\Response\ChannelTopicResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class JoinHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelNameValidator $channelNames,
        private ChannelBroadcaster $broadcaster,
        private ChannelNamesResponseFactory $namesResponses,
        private ChannelTopicResponseFactory $topicResponses,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function command(): string
    {
        return 'JOIN';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingParametersResponse($context);
            return;
        }

        foreach (explode(',', $message->parameter(0)) as $channelName) {
            $this->joinChannel($context, $channelName);
        }
    }

    private function sendMissingParametersResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
        );
    }

    private function joinChannel(CommandContext $context, string $channelName): void
    {
        if (! $this->channelNames->isValid($channelName)) {
            $this->sendInvalidChannelResponse($context, $channelName);
            return;
        }

        $channel = $this->channels->find($channelName);

        if ($channel?->hasMember($context->client)) {
            return;
        }

        $channel = $this->channels->join($channelName, $context->client);

        $this->broadcastJoin($context, $channel);
        $this->sendInitialChannelState($context, $channel);
    }

    private function sendInvalidChannelResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->errors->noSuchChannel($context->responseTarget(), $channelName),
        );
    }

    private function broadcastJoin(CommandContext $context, Channel $channel): void
    {
        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: $this->command(),
                parameters: [$channel->name],
                source: $context->actorNickname(),
            ),
        );
    }

    private function sendInitialChannelState(CommandContext $context, Channel $channel): void
    {
        $context->connection->sendMany(
            [
                ...$this->topicResponses->createExistingTopicResponses($context->responseTarget(), $channel),
                ...$this->namesResponses->createNamesResponses(
                    $context->responseTarget(),
                    $context->client,
                    $channel,
                ),
            ],
        );
    }
}
