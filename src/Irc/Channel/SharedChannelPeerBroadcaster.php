<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\Message;

final readonly class SharedChannelPeerBroadcaster
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
    ) {}

    public function broadcast(Client $client, Message $message): void
    {
        $peers = [];

        foreach ($this->channels->channelsFor($client) as $channel) {
            foreach ($channel->members() as $membership) {
                if ($membership->client === $client) {
                    continue;
                }

                $clientId = spl_object_id($membership->client);
                $peers[$clientId] = $membership->client;
            }
        }

        foreach ($peers as $peer) {
            $this->clients->connectionFor($peer)?->send($message);
        }
    }
}
