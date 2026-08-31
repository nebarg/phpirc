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
    public function createResponses(string $target, Channel $channel): array
    {
        $names = [];

        foreach ($channel->memberships() as $membership) {
            $nickname = $membership->client->nickname;

            if ($nickname === null) {
                continue;
            }

            $names[] = ($membership->isOperator ? '@' : '') . $nickname;
        }

        return [
            $this->responses->create(
                code: ResponseCode::NamesReply,
                target: $target,
                parameters: ['=', $channel->name],
                text: implode(' ', $names),
            ),
            $this->responses->create(
                code: ResponseCode::EndOfNames,
                target: $target,
                parameters: [$channel->name],
            ),
        ];
    }
}
