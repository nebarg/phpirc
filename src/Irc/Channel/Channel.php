<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;

final class Channel
{
    /** @var array<int, Membership> */
    private array $members = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function join(Client $client): Membership
    {
        $clientId = $this->clientId($client);

        if (isset($this->members[$clientId])) {
            return $this->members[$clientId];
        }

        return $this->members[$clientId] = new Membership(
            client: $client,
            isOperator: $this->isEmpty(),
        );
    }

    public function leave(Client $client): bool
    {
        $clientId = $this->clientId($client);

        if (! isset($this->members[$clientId])) {
            return false;
        }

        unset($this->members[$clientId]);

        return true;
    }

    public function membershipFor(Client $client): ?Membership
    {
        return $this->members[$this->clientId($client)] ?? null;
    }

    public function has(Client $client): bool
    {
        return isset($this->members[$this->clientId($client)]);
    }

    /** @return list<Membership> */
    public function members(): array
    {
        return array_values($this->members);
    }

    public function isEmpty(): bool
    {
        return $this->members === [];
    }

    public function memberCount(): int
    {
        return count($this->members);
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }
}
