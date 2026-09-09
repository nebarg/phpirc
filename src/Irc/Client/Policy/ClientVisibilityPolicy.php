<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Policy;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Mode\UserMode;

final readonly class ClientVisibilityPolicy
{
    public function __construct(
        private ChannelRegistry $channels,
    ) {}

    public function canSee(Client $requester, Client $client): bool
    {
        if ($requester === $client || ! $client->hasMode(UserMode::Invisible)) {
            return true;
        }

        foreach ($this->channels->channelsFor($requester) as $channel) {
            if ($channel->hasMember($client)) {
                return true;
            }
        }

        return false;
    }

    public function canSeeInChannel(Client $requester, Client $client, Channel $channel): bool
    {
        if ($requester === $client || ! $client->hasMode(UserMode::Invisible)) {
            return true;
        }

        return $channel->hasMember($requester);
    }
}
