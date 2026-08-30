<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Protocol\CaseMapping\CaseMapper;

final class ClientRegistry
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function __construct(
        private CaseMapper $caseMapper,
    ) {}

    public function claimNickname(Client $client, string $nickname): bool
    {
        $clientId = $this->clientId($nickname);
        $owner = $this->clients[$clientId] ?? null;

        if ($owner !== null && $owner !== $client) {
            return false;
        }

        if ($client->nickname !== null) {
            $this->release($client);
        }

        $client->setNickname($nickname);
        $this->clients[$clientId] = $client;

        return true;
    }

    public function findByNickname(string $nickname): ?Client
    {
        return $this->clients[$this->clientId($nickname)] ?? null;
    }

    public function release(Client $client): void
    {
        if ($client->nickname === null) {
            return;
        }

        $clientId = $this->clientId($client->nickname);

        if (($this->clients[$clientId] ?? null) === $client) {
            unset($this->clients[$clientId]);
        }
    }

    private function clientId(string $nickname): string
    {
        return $this->caseMapper->normalise($nickname);
    }
}
