<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelListResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    /**
     * @param list<Channel> $channels
     * @return list<Message>
     */
    public function createResponses(string $target, array $channels): array
    {
        $list = [];

        foreach ($channels as $channel) {
            $list[] = $this->responses->create(
                code: ResponseCode::ListEntry,
                target: $target,
                parameters: [
                    $channel->name,
                    (string) $channel->memberCount(),
                ],
                text: '',
            );
        }

        return [
            $this->responses->create(
                code: ResponseCode::ListStart,
                target: $target,
                parameters: ['Channel'],
            ),
            ...$list,
            $this->responses->create(
                code: ResponseCode::ListEnd,
                target: $target,
            ),
        ];
    }
}
