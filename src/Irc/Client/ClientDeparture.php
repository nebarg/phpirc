<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Protocol\Message;

final readonly class ClientDeparture
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
    ) {}

    public function depart(Client $client, string $reason): void
    {
        try {
            if ($client->nickname !== null) {
                $this->broadcaster->broadcastToSharedChannelPeers(
                    $client,
                    new Message(
                        command: 'QUIT',
                        parameters: [$reason],
                        source: $client->nickname,
                    ),
                );
            }
        } finally {
            $this->channels->leaveAll($client);
            $this->clients->unregister($client);
        }
    }
}
