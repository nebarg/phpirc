<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use LogicException;
use PhpIrc\Irc\Protocol\CaseMapping\CaseMapper;
use PhpIrc\Irc\Transport\Connection;

final class ClientRegistry
{
    /** @var array<int, ConnectedClient> */
    private array $clientsById = [];

    /** @var array<string, int> */
    private array $clientIdsByNickname = [];

    public function __construct(
        private CaseMapper $caseMapper,
    ) {}

    public function register(Client $client, Connection $connection): void
    {
        $clientId = $this->clientId($client);

        if (isset($this->clientsById[$clientId])) {
            throw new LogicException('Client is already registered.');
        }

        $this->clientsById[$clientId] = new ConnectedClient(
            client: $client,
            connection: $connection,
        );
    }

    public function connectionFor(Client $client): ?Connection
    {
        $connectedClient = $this->clientsById[$this->clientId($client)] ?? null;

        return $connectedClient?->connection;
    }

    public function unregister(Client $client): void
    {
        $clientId = $this->clientId($client);

        $this->releaseNickname($client);

        unset($this->clientsById[$clientId]);
    }

    public function claimNickname(Client $client, string $nickname): bool
    {
        $nicknameKey = $this->nicknameKey($nickname);
        $clientId = $this->clientId($client);
        $ownerId = $this->clientIdsByNickname[$nicknameKey] ?? null;

        if (! isset($this->clientsById[$clientId])) {
            throw new LogicException('Client must be registered before claiming a nickname.');
        }

        if ($ownerId !== null && $ownerId !== $clientId) {
            return false;
        }

        if ($client->nickname !== null) {
            $this->releaseNickname($client);
        }

        $client->setNickname($nickname);
        $this->clientIdsByNickname[$nicknameKey] = $clientId;

        return true;
    }

    public function findByNickname(string $nickname): ?Client
    {
        $clientId = $this->clientIdsByNickname[$this->nicknameKey($nickname)] ?? null;

        if ($clientId === null) {
            return null;
        }

        $connectedClient = $this->clientsById[$clientId] ?? null;

        return $connectedClient?->client;
    }

    private function releaseNickname(Client $client): void
    {
        if ($client->nickname === null) {
            return;
        }

        $nicknameKey = $this->nicknameKey($client->nickname);
        $clientId = $this->clientId($client);

        if (($this->clientIdsByNickname[$nicknameKey] ?? null) === $clientId) {
            unset($this->clientIdsByNickname[$nicknameKey]);
        }
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }

    private function nicknameKey(string $nickname): string
    {
        return $this->caseMapper->normalise($nickname);
    }
}
