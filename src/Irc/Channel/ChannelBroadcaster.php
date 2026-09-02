<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnectionRegistry;

final readonly class ChannelBroadcaster
{
    public function __construct(
        private ClientConnectionRegistry $connections,
    ) {}

    public function broadcast(Channel $channel, Message $message): void
    {
        foreach ($channel->memberships() as $member) {
            $connection = $this->connections->find($member->client);

            $connection?->send($message);
        }
    }

    public function broadcastExcept(Channel $channel, Message $message, Client $exception): void
    {
        foreach ($channel->memberships() as $member) {
            if ($member->client === $exception) {
                continue;
            }

            $connection = $this->connections->find($member->client);

            $connection?->send($message);
        }
    }
}
