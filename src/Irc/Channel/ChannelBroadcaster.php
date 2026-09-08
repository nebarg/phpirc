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
        foreach ($channel->members() as $member) {
            $this->clients->connectionFor($member->client)?->send($message);
        }
    }

    public function broadcastExcept(Channel $channel, Message $message, Client $exception): void
    {
        foreach ($channel->members() as $member) {
            if ($member->client === $exception) {
                continue;
            }

            $this->clients->connectionFor($member->client)?->send($message);
        }
    }
}
