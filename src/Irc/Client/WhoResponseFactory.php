<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class WhoResponseFactory
{
    public function __construct(
        private ServerName $serverName,
        private NumericResponseFactory $responses,
    ) {}

    public function createClientReply(string $target, Client $client): Message
    {
        return $this->createReply(
            target: $target,
            client: $client,
            channelName: '*',
            isOperator: false,
        );
    }

    public function createChannelMemberReply(
        string $target,
        Channel $channel,
        Membership $membership,
    ): Message {
        return $this->createReply(
            target: $target,
            client: $membership->client,
            channelName: $channel->name,
            isOperator: $membership->isOperator,
        );
    }

    private function createReply(
        string $target,
        Client $client,
        string $channelName,
        bool $isOperator,
    ): Message {
        return $this->responses->create(
            code: ResponseCode::WhoReply,
            target: $target,
            parameters: [
                $channelName,
                $client->username ?? '*',
                $client->hostname,
                $this->serverName->value,
                $client->nickname ?? '*',
                'H' . ($isOperator ? '@' : ''),
            ],
            text: '0 ' . ($client->realName ?? ''),
        );
    }

    public function createEndResponse(string $target, string $mask): Message
    {
        return $this->responses->create(
            code: ResponseCode::EndOfWho,
            target: $target,
            parameters: [$mask],
        );
    }
}
