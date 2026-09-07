<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Policy;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Client\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelAccessPolicyTest extends TestCase
{
    private ChannelAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ChannelAccessPolicy();
    }

    #[Test]
    public function only_allowed_permissions_are_not_denied(): void
    {
        $this->assertFalse(ChannelPermission::Allowed->isDenied());
        $this->assertTrue(ChannelPermission::NotMember->isDenied());
        $this->assertTrue(ChannelPermission::InsufficientPrivileges->isDenied());
    }

    #[Test]
    public function channel_members_can_send_when_the_channel_is_not_moderated(): void
    {
        [$channel, , $member] = $this->channelWithOperatorAndMember();

        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkMessageDelivery($channel, $member),
        );
    }

    #[Test]
    public function no_external_messages_mode_rejects_an_outsider(): void
    {
        $channel = new Channel('#php');

        $this->assertSame(
            ChannelPermission::NotMember,
            $this->policy->checkMessageDelivery($channel, new Client()),
        );
    }

    #[Test]
    public function an_outsider_can_send_when_no_external_messages_mode_is_disabled(): void
    {
        $channel = new Channel('#php');
        $channel->disableMode(ChannelMode::NoExternalMessages);

        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkMessageDelivery($channel, new Client()),
        );
    }

    #[Test]
    public function moderated_mode_rejects_an_outsider_even_when_external_messages_are_allowed(): void
    {
        $channel = new Channel('#php');
        $channel->disableMode(ChannelMode::NoExternalMessages);
        $channel->enableMode(ChannelMode::Moderated);

        $this->assertSame(
            ChannelPermission::NotMember,
            $this->policy->checkMessageDelivery($channel, new Client()),
        );
    }

    #[Test]
    public function moderated_mode_rejects_an_unprivileged_member(): void
    {
        [$channel, , $member] = $this->channelWithOperatorAndMember();
        $channel->enableMode(ChannelMode::Moderated);

        $this->assertSame(
            ChannelPermission::InsufficientPrivileges,
            $this->policy->checkMessageDelivery($channel, $member),
        );
    }

    #[Test]
    public function moderated_mode_allows_operators_and_voiced_members(): void
    {
        [$channel, $operator, $member] = $this->channelWithOperatorAndMember();
        $membership = $channel->membershipFor($member);
        $this->assertNotNull($membership);
        $membership->grant(MembershipMode::Voice);
        $channel->enableMode(ChannelMode::Moderated);

        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkMessageDelivery($channel, $operator),
        );
        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkMessageDelivery($channel, $member),
        );
    }

    #[Test]
    public function topic_changes_require_membership(): void
    {
        $this->assertSame(
            ChannelPermission::NotMember,
            $this->policy->checkTopicChange(new Channel('#php'), new Client()),
        );
    }

    #[Test]
    public function protected_topics_require_operator_privileges(): void
    {
        [$channel, $operator, $member] = $this->channelWithOperatorAndMember();

        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkTopicChange($channel, $operator),
        );
        $this->assertSame(
            ChannelPermission::InsufficientPrivileges,
            $this->policy->checkTopicChange($channel, $member),
        );
    }

    #[Test]
    public function any_member_can_change_an_unprotected_topic(): void
    {
        [$channel, , $member] = $this->channelWithOperatorAndMember();
        $channel->disableMode(ChannelMode::ProtectedTopic);

        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkTopicChange($channel, $member),
        );
    }

    #[Test]
    public function mode_changes_require_membership_and_operator_privileges(): void
    {
        [$channel, $operator, $member] = $this->channelWithOperatorAndMember();

        $this->assertSame(
            ChannelPermission::NotMember,
            $this->policy->checkModeChange($channel, new Client()),
        );
        $this->assertSame(
            ChannelPermission::InsufficientPrivileges,
            $this->policy->checkModeChange($channel, $member),
        );
        $this->assertSame(
            ChannelPermission::Allowed,
            $this->policy->checkModeChange($channel, $operator),
        );
    }

    /** @return array{Channel, Client, Client} */
    private function channelWithOperatorAndMember(): array
    {
        $channel = new Channel('#php');
        $operator = new Client();
        $member = new Client();
        $channel->join($operator);
        $channel->join($member);

        return [$channel, $operator, $member];
    }
}
