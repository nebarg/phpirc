<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Policy;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;

final readonly class ChannelAccessPolicy
{
    public function checkMessageDelivery(Channel $channel, Client $client): ChannelPermission
    {
        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return $channel->hasMode(ChannelMode::NoExternalMessages) || $channel->hasMode(ChannelMode::Moderated)
                ? ChannelPermission::NotMember
                : ChannelPermission::Allowed;
        }

        if (! $channel->hasMode(ChannelMode::Moderated)) {
            return ChannelPermission::Allowed;
        }

        return $membership->has(MembershipMode::Operator) || $membership->has(MembershipMode::Voice)
            ? ChannelPermission::Allowed
            : ChannelPermission::InsufficientPrivileges;
    }

    public function checkTopicChange(Channel $channel, Client $client): ChannelPermission
    {
        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return ChannelPermission::NotMember;
        }

        if (! $channel->hasMode(ChannelMode::ProtectedTopic)) {
            return ChannelPermission::Allowed;
        }

        return $this->checkOperatorPrivileges($membership);
    }

    public function checkModeChange(Channel $channel, Client $client): ChannelPermission
    {
        return $this->checkOperatorAction($channel, $client);
    }

    public function checkKick(Channel $channel, Client $client): ChannelPermission
    {
        return $this->checkOperatorAction($channel, $client);
    }

    private function checkOperatorAction(Channel $channel, Client $client): ChannelPermission
    {
        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return ChannelPermission::NotMember;
        }

        return $this->checkOperatorPrivileges($membership);
    }

    private function checkOperatorPrivileges(Membership $membership): ChannelPermission
    {
        return $membership->has(MembershipMode::Operator)
            ? ChannelPermission::Allowed
            : ChannelPermission::InsufficientPrivileges;
    }
}
