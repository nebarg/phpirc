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
        return $this->channels[$this->normaliseChannelName($name)] ?? null;
    }

    public function join(string $name, Client $client): Channel
    {
        $channelId = $this->normaliseChannelName($name);
        $channel = $this->channels[$channelId] ?? new Channel($name);
        $this->channels[$channelId] = $channel;

        $channel->join($client);

        return $channel;
    }

    public function leave(Channel $channel, Client $client): bool
    {
        $channelId = $this->normaliseChannelName($channel->name);
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

    private function normaliseChannelName(string $name): string
    {
        return $this->caseMapper->normalise($name);
    }
}
