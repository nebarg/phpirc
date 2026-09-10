<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class WhoisResponseFactory
{
    public function __construct(
        private ServerConfig $config,
        private NumericResponseFactory $responses,
        private MessageSize $messageSize,
        private AwayResponseFactory $awayResponses,
    ) {}

    /**
     * @param list<Channel> $channels
     * @return list<Message>
     */
    public function createWhoisResponses(
        string $target,
        string $requestedNickname,
        Client $client,
        array $channels,
    ): array {
        $nickname = $client->nickname ?? $requestedNickname;

        return [
            $this->createUserResponse($target, $nickname, $client),
            $this->createServerResponse($target, $nickname),
            ...$this->createAwayResponses($target, $client),
            ...$this->createChannelResponses($target, $nickname, $client, $channels),
            $this->createEndOfWhoisResponse($target, $requestedNickname),
        ];
    }

    public function createEndOfWhoisResponse(string $target, string $requestedNickname): Message
    {
        return $this->responses->create(
            code: ResponseCode::EndOfWhois,
            target: $target,
            parameters: [$requestedNickname],
        );
    }

    private function createUserResponse(string $target, string $nickname, Client $client): Message
    {
        return $this->responses->create(
            code: ResponseCode::WhoisUser,
            target: $target,
            parameters: [
                $nickname,
                $client->username ?? '*',
                $client->hostname,
                '*',
            ],
            text: $client->realName ?? '',
        );
    }

    private function createServerResponse(string $target, string $nickname): Message
    {
        return $this->responses->create(
            code: ResponseCode::WhoisServer,
            target: $target,
            parameters: [$nickname, $this->config->serverName->value],
            text: $this->config->networkName,
        );
    }

    /** @return list<Message> */
    private function createAwayResponses(string $target, Client $client): array
    {
        if (! $client->isAway()) {
            return [];
        }

        return [$this->awayResponses->createClientIsAwayResponse($target, $client)];
    }

    /**
     * @param list<Channel> $channels
     * @return list<Message>
     */
    private function createChannelResponses(
        string $target,
        string $nickname,
        Client $client,
        array $channels,
    ): array {
        $responses = [];
        $channelNames = [];

        foreach ($channels as $channel) {
            $membership = $channel->membershipFor($client);

            if ($membership === null) {
                continue;
            }

            $channelName = $membership->highestPrefix() . $channel->name;

            if (! $this->canAppendChannel($target, $nickname, $channelNames, $channelName)) {
                $responses[] = $this->createChannelResponse($target, $nickname, $channelNames);
                $channelNames = [];
            }

            $channelNames[] = $channelName;
        }

        if ($channelNames !== []) {
            $responses[] = $this->createChannelResponse($target, $nickname, $channelNames);
        }

        return $responses;
    }

    /** @param list<string> $channelNames */
    private function createChannelResponse(string $target, string $nickname, array $channelNames): Message
    {
        return $this->responses->create(
            code: ResponseCode::WhoisChannels,
            target: $target,
            parameters: [$nickname],
            text: implode(' ', $channelNames),
        );
    }

    /** @param list<string> $channelNames */
    private function canAppendChannel(
        string $target,
        string $nickname,
        array $channelNames,
        string $channelName,
    ): bool {
        if ($channelNames === []) {
            return true;
        }

        return $this->messageSize->fits(
            $this->createChannelResponse($target, $nickname, [...$channelNames, $channelName]),
        );
    }
}
