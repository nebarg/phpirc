<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;

final class Channel
{
    /** @var array<int, Membership> */
    private array $members = [];

    /** @var array<string, ChannelMode> */
    private array $modes = [];

    public private(set) ?Topic $topic = null;

    public readonly DateTimeImmutable $createdAt;

    public function __construct(
        public readonly string $name,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->modes = [
            ChannelMode::NoExternalMessages->value => ChannelMode::NoExternalMessages,
            ChannelMode::ProtectedTopic->value => ChannelMode::ProtectedTopic,
        ];
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

    public function hasMembers(): bool
    {
        return $this->members !== [];
    }

    /** @return list<Membership> */
    public function members(): array
    {
        return array_values($this->members);
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

    public function enableMode(ChannelMode $mode): bool
    {
        if ($this->hasMode($mode)) {
            return false;
        }

        $this->modes[$mode->value] = $mode;

        return true;
    }

    public function disableMode(ChannelMode $mode): bool
    {
        if (! $this->hasMode($mode)) {
            return false;
        }

        unset($this->modes[$mode->value]);

        return true;
    }

    public function hasMode(ChannelMode $mode): bool
    {
        return isset($this->modes[$mode->value]);
    }

    /** @return list<ChannelMode> */
    public function modes(): array
    {
        return array_values($this->modes);
    }

    public function canSendMessage(Client $client): bool
    {
        $membership = $this->membershipFor($client);

        if ($this->hasMode(ChannelMode::Moderated)) {
            return $membership !== null && ($membership->has(MembershipMode::Operator) || $membership->has(MembershipMode::Voice));
        }

        return $membership !== null || ! $this->hasMode(ChannelMode::NoExternalMessages);
    }

    private function clientId(Client $client): int
    {
        return spl_object_id($client);
    }
}
