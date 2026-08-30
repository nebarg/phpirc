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
        $normalisedNickname = $this->normaliseNickname($nickname);
        $owner = $this->clients[$normalisedNickname] ?? null;

        if ($owner !== null && $owner !== $client) {
            return false;
        }

        if ($client->nickname !== null) {
            $this->release($client);
        }

        $client->setNickname($nickname);
        $this->clients[$normalisedNickname] = $client;

        return true;
    }

    public function findByNickname(string $nickname): ?Client
    {
        return $this->clients[$this->normaliseNickname($nickname)] ?? null;
    }

    public function release(Client $client): void
    {
        if ($client->nickname === null) {
            return;
        }

        $nickname = $this->normaliseNickname($client->nickname);

        if (($this->clients[$nickname] ?? null) === $client) {
            unset($this->clients[$nickname]);
        }
    }

    private function normaliseNickname(string $nickname): string
    {
        return $this->caseMapper->normalise($nickname);
    }
}
