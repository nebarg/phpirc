<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelNamesResponseFactory;
use PhpIrc\Irc\Channel\ChannelNameValidator;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
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
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $channels = $message->parameter(0);

        foreach (explode(',', $channels) as $channelName) {
            if (! $this->channelNames->isValid($channelName)) {
                $context->connection->send(
                    $this->errors->noSuchChannel($context->responseTarget(), $channelName),
                );

                continue;
            }

            $channel = $this->channels->find($channelName);

            if ($channel?->hasMember($context->client)) {
                continue;
            }

            $channel = $this->channels->join($channelName, $context->client);

            $this->broadcaster->broadcast(
                $channel,
                new Message(
                    command: $this->command(),
                    parameters: [$channel->name],
                    source: $context->client->nickname,
                ),
            );

            array_map(
                $context->connection->send(...),
                [
                    ...$this->topicResponses->createExistingTopicResponses($context->responseTarget(), $channel),
                    ...$this->namesResponses->createResponses($context->responseTarget(), $channel),
                ],
            );
        }
    }
}
