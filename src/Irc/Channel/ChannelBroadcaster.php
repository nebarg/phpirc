<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\Message;

final readonly class ChannelBroadcaster
{
    public function __construct(
        private ClientRegistry $clients,
    ) {}

    public function broadcast(Channel $channel, Message $message): void
    {
        foreach ($channel->memberships() as $member) {
            $connection = $this->clients->connectionFor($member->client);

            $connection?->send($message);
        }
    }

    public function broadcastExcept(Channel $channel, Message $message, Client $exception): void
    {
        foreach ($channel->memberships() as $member) {
            if ($member->client === $exception) {
                continue;
            }

            $connection = $this->clients->connectionFor($member->client);

            $connection?->send($message);
        }
    }
}
