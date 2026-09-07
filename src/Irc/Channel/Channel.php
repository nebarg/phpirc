<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;

final class Channel
{
    /** @var array<int, Membership> */
    private array $members = [];

    public private(set) ?Topic $topic = null;

    public readonly DateTimeImmutable $createdAt;

    public function __construct(
        public readonly string $name,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function join(Client $client): Membership
    {
        $clientId = $this->clientId($client);

        if (isset($this->members[$clientId])) {
            return $this->members[$clientId];
        }

        $membership = new Membership($client);

        if (! $this->hasMembers()) {
            $membership->grant(MembershipMode::Operator);
        }

        return $this->members[$clientId] = $membership;
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

    public function hasMember(Client $client): bool
    {
        return isset($this->members[$this->clientId($client)]);
    }

    /** @return list<Membership> */
    public function members(): array
    {
        return array_values($this->members);
    }

    public function hasMembers(): bool
    {
        return $this->members !== [];
    }

    public function memberCount(): int
    {
        return count($this->members);
    }

    public function setTopic(string $topic, string $byNickname): void
    {
        $this->topic = new Topic(
            text: $topic,
            setBy: $byNickname,
            setAt: new DateTimeImmutable(),
        );
    }

    public function clearTopic(): void
    {
        $this->topic = null;
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }
}
