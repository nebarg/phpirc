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
        private ChannelRegistry $channels,
    ) {}

    public function broadcast(Channel $channel, Message $message): void
    {
        foreach ($channel->members() as $member) {
            $connection = $this->clients->connectionFor($member->client);

            $connection?->send($message);
        }
    }

    public function broadcastExcept(Channel $channel, Message $message, Client $exception): void
    {
        foreach ($channel->members() as $member) {
            if ($member->client === $exception) {
                continue;
            }

            $connection = $this->clients->connectionFor($member->client);

            $connection?->send($message);
        }
    }

    public function broadcastToSharedChannelPeers(Client $client, Message $message): void
    {
        $peers = [];

        foreach ($this->channels->channelsFor($client) as $channel) {
            foreach ($channel->members() as $membership) {
                if ($membership->client === $client) {
                    continue;
                }

                $peers[$this->clientId($membership->client)] = $membership->client;
            }
        }

        foreach ($peers as $peer) {
            $this->clients->connectionFor($peer)?->send($message);
        }
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }
}
