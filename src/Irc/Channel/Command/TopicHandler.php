<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class TopicHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private ChannelTopicResponseFactory $topicResponses,
        private NumericResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'TOPIC';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissing(0)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NeedMoreParameters,
                    target: $context->responseTarget(),
                    parameters: [$this->command()],
                ),
            );

            return;
        }

        $channel = $this->channels->find($message->parameter(0));

        if ($channel === null) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoSuchChannel,
                    target: $context->responseTarget(),
                    parameters: [$message->parameter(0)],
                ),
            );

            return;
        }

        $topic = $message->optionalParameter(1);

        if ($topic === null) {
            array_map(
                $context->connection->send(...),
                $this->topicResponses->createResponses($context->responseTarget(), $channel),
            );

            return;
        }

        if (! $channel->has($context->client)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NotOnChannel,
                    target: $context->responseTarget(),
                    parameters: [$channel->name],
                ),
            );

            return;
        }

        if ($topic === '') {
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
