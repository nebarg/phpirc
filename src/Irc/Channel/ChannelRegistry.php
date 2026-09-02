<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\CaseMapping\CaseMapper;

final class ChannelRegistry
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function __construct(
        private readonly CaseMapper $caseMapper,
    ) {}

    public function find(string $name): ?Channel
    {
        return $this->channels[$this->channelId($name)] ?? null;
    }

    /** @return list<Channel> */
    public function channelsFor(Client $client): array
    {
        $channels = [];

        foreach ($this->channels as $channel) {
            if (! $channel->has($client)) {
                continue;
            }

            $channels[] = $channel;
        }

        return $channels;
    }

    public function join(string $name, Client $client): Channel
    {
        $channelId = $this->channelId($name);
        $channel = $this->channels[$channelId] ?? new Channel($name);
        $this->channels[$channelId] = $channel;

        $channel->join($client);

        return $channel;
    }

    public function leave(Channel $channel, Client $client): bool
    {
        $channelId = $this->channelId($channel->name);
        $existingChannel = $this->channels[$channelId] ?? null;

        if ($existingChannel !== $channel) {
            return false;
        }

        $left = $channel->leave($client);

        if ($channel->isEmpty()) {
            unset($this->channels[$channelId]);
        }

        return $left;
    }

    public function leaveAll(Client $client): void
    {
        foreach ($this->channels as $channel) {
            $this->leave($channel, $client);
        }
    }

    /** @return list<Channel> */
    public function all(): array
    {
        return array_values($this->channels);
    }

    private function channelId(string $name): string
    {
        return $this->caseMapper->normalise($name);
    }
}
