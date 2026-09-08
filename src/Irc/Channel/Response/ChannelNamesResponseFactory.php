<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelNamesResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
        private MessageSize $messageSize,
    ) {}

    /** @return list<Message> */
    public function createNamesResponses(string $target, Channel $channel): array
    {
        $messages = [];
        $names = [];

        foreach ($channel->members() as $membership) {
            $nickname = $membership->client->nickname;

            if ($nickname === null) {
                continue;
            }

            $name = $membership->highestPrefix() . $nickname;

            if (! $this->canAppendName($target, $channel->name, $names, $name)) {
                $messages[] = $this->createNamesResponse($target, $channel->name, $names);
                $names = [];
            }

            $names[] = $name;
        }

        $messages[] = $this->createNamesResponse($target, $channel->name, $names);
        $messages[] = $this->createEndOfNamesResponse($target, $channel->name);

        return $messages;
    }

    public function createEndOfNamesResponse(string $target, string $channelName): Message
    {
        return $this->responses->create(
            code: ResponseCode::EndOfNames,
            target: $target,
            parameters: [$channelName],
        );
    }

    /** @param list<string> $names */
    private function createNamesResponse(string $target, string $channelName, array $names): Message
    {
        return $this->responses->create(
            code: ResponseCode::NamesReply,
            target: $target,
            parameters: ['=', $channelName],
            text: implode(' ', $names),
        );
    }

    /** @param list<string> $names */
    private function canAppendName(string $target, string $channelName, array $names, string $name): bool
    {
        if ($names === []) {
            return true;
        }

        return $this->messageSize->fits(
            $this->createNamesResponse($target, $channelName, [...$names, $name]),
        );
    }
}
