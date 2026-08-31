<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;

final readonly class ClientConnectionLifecycle
{
    public function __construct(
        private ClientRegistry $clients,
        private ClientConnectionRegistry $connections,
        private ChannelRegistry $channels,
    ) {}

    public function connected(Client $client, Connection $connection): void
    {
        $this->connections->register($client, $connection);
    }

    public function disconnected(Client $client, Connection $connection): void
    {
        $this->channels->leaveAll($client);
        $this->clients->release($client);
        $this->connections->unregister($client, $connection);
    }
}
