<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class TopicHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private ChannelTopicResponseFactory $topicResponses,
        private NumericErrorResponseFactory $errors,
        private ChannelAccessPolicy $channelAccess,
        private ChannelPermissionResponseFactory $permissionResponses,
    ) {}

    public function command(): string
    {
        return 'TOPIC';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $channel = $this->channels->find($message->parameter(0));

        if ($channel === null) {
            $context->connection->send(
                $this->errors->noSuchChannel($context->responseTarget(), $message->parameter(0)),
            );

            return;
        }

        if ($message->isParameterMissing(1)) {
            $context->connection->sendMany(
                $this->topicResponses->createCurrentTopicResponses($context->responseTarget(), $channel),
            );

            return;
        }

        $topic = $message->parameter(1);

        $permission = $this->channelAccess->checkTopicChange($channel, $context->client);

        if ($permission->isDenied()) {
            $context->connection->send(
                $this->permissionResponses->createTopicChangeDeniedResponse(
                    $permission,
                    $context->responseTarget(),
                    $channel,
                ),
            );

            return;
        }

        if ($message->isParameterEmpty(1)) {
            $channel->clearTopic();
        } else {
            $channel->setTopic($topic, $context->responseTarget());
        }

        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: $this->command(),
                parameters: [$channel->name, $topic],
                source: $context->responseTarget(),
            ),
        );
    }
}
