<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Channel\Response\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\Response\ChannelTopicResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Config\ServerLimits;
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
        private ServerLimits $limits,
    ) {}

    public function command(): string
    {
        return 'TOPIC';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingParametersResponse($context);
            return;
        }

        $channelName = $message->parameter(0);
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $this->sendUnknownChannelResponse($context, $channelName);
            return;
        }

        if ($message->isParameterMissing(1)) {
            $this->sendCurrentTopicResponses($context, $channel);
            return;
        }

        $permission = $this->channelAccess->canChangeTopic($channel, $context->client);

        if ($permission->isDenied()) {
            $this->sendPermissionDeniedResponse($context, $channel, $permission);
            return;
        }

        $this->changeTopic($context, $channel, $message);
    }

    private function sendMissingParametersResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
        );
    }

    private function sendUnknownChannelResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->errors->noSuchChannel($context->responseTarget(), $channelName),
        );
    }

    private function sendCurrentTopicResponses(CommandContext $context, Channel $channel): void
    {
        $context->connection->sendMany(
            $this->topicResponses->createCurrentTopicResponses($context->responseTarget(), $channel),
        );
    }

    private function sendPermissionDeniedResponse(
        CommandContext $context,
        Channel $channel,
        ChannelPermission $permission,
    ): void {
        $context->connection->send(
            $this->permissionResponses->createTopicChangeDeniedResponse(
                $permission,
                $context->responseTarget(),
                $channel,
            ),
        );
    }

    private function changeTopic(CommandContext $context, Channel $channel, Message $message): void
    {
        $actorNickname = $context->actorNickname();
        $topic = $this->limits->truncateTopic($message->parameter(1));

        if ($message->isParameterEmpty(1)) {
            $channel->clearTopic();
        } else {
            $channel->setTopic($topic, $actorNickname);
        }

        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: $this->command(),
                parameters: [$channel->name, $topic],
                source: $actorNickname,
            ),
        );
    }
}
