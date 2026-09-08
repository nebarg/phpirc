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
    public function canSendMessage(Channel $channel, Client $client): ChannelPermission
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

    public function canChangeTopic(Channel $channel, Client $client): ChannelPermission
    {
        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return ChannelPermission::NotMember;
        }

        if (! $channel->hasMode(ChannelMode::ProtectedTopic)) {
            return ChannelPermission::Allowed;
        }

        return $this->canUseOperatorPrivileges($membership);
    }

    public function canChangeMode(Channel $channel, Client $client): ChannelPermission
    {
        return $this->canPerformOperatorAction($channel, $client);
    }

    public function canKick(Channel $channel, Client $client): ChannelPermission
    {
        return $this->canPerformOperatorAction($channel, $client);
    }

    private function canPerformOperatorAction(Channel $channel, Client $client): ChannelPermission
    {
        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return ChannelPermission::NotMember;
        }

        return $this->canUseOperatorPrivileges($membership);
    }

    private function canUseOperatorPrivileges(Membership $membership): ChannelPermission
    {
        return $membership->has(MembershipMode::Operator)
            ? ChannelPermission::Allowed
            : ChannelPermission::InsufficientPrivileges;
    }
}
