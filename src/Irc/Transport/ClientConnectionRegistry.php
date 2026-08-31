<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Client;

final class ClientConnectionRegistry
{
    /** @var array<int, Connection> */
    private array $connections = [];

    public function register(Client $client, Connection $connection): void
    {
        $this->connections[$this->clientId($client)] = $connection;
    }

    public function find(Client $client): ?Connection
    {
        return $this->connections[$this->clientId($client)] ?? null;
    }

    public function unregister(Client $client, Connection $connection): void
    {
        $clientId = $this->clientId($client);

        if (($this->connections[$clientId] ?? null) === $connection) {
            unset($this->connections[$clientId]);
        }
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }
}
