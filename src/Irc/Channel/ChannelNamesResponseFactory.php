<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelNamesResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    /** @return list<Message> */
    public function createNamesResponses(string $target, Channel $channel): array
    {
        $names = [];

        foreach ($channel->members() as $membership) {
            $nickname = $membership->client->nickname;

            if ($nickname === null) {
                continue;
            }

            $names[] = $membership->highestPrefix() . $nickname;
        }

        return [
            $this->responses->create(
                code: ResponseCode::NamesReply,
                target: $target,
                parameters: ['=', $channel->name],
                text: implode(' ', $names),
            ),
            $this->createEndOfNamesResponse($target, $channel->name),
        ];
    }

    public function createEndOfNamesResponse(string $target, string $channelName): Message
    {
        return $this->responses->create(
            code: ResponseCode::EndOfNames,
            target: $target,
            parameters: [$channelName],
        );
    }
}
