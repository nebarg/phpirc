<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelTopicResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    /** @return list<Message> */
    public function createResponses(string $target, Channel $channel): array
    {
        if ($channel->topic === null) {
            return [
                $this->responses->create(
                    code: ResponseCode::NoTopic,
                    target: $target,
                    parameters: [$channel->name],
                ),
            ];
        }

        return $this->createExistingTopicResponses($target, $channel);
    }

    /** @return list<Message> */
    public function createExistingTopicResponses(string $target, Channel $channel): array
    {
        if ($channel->topic === null) {
            return [];
        }

        return [
            $this->responses->create(
                code: ResponseCode::Topic,
                target: $target,
                parameters: [$channel->name],
                text: $channel->topic->text,
            ),
            $this->responses->create(
                code: ResponseCode::TopicWhoTime,
                target: $target,
                parameters: [
                    $channel->name,
                    $channel->topic->setBy,
                    (string) $channel->topic->setAt->getTimestamp(),
                ],
            ),
        ];
    }
}
