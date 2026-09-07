<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelTest extends TestCase
{
    #[Test]
    public function it_preserves_its_name(): void
    {
        $this->assertSame('#PHP', new Channel('#PHP')->name);
    }

    #[Test]
    public function it_preserves_its_creation_time(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-06T10:15:30+00:00');

        $this->assertSame($createdAt, new Channel('#php', $createdAt)->createdAt);
    }

    #[Test]
    public function it_makes_the_first_member_an_operator(): void
    {
        $channel = new Channel('#php');

        $membership = $channel->join(new Client());

        $this->assertTrue($membership->has(MembershipMode::Operator));
    }

    #[Test]
    public function it_does_not_make_later_members_operators(): void
    {
        $channel = new Channel('#php');
        $channel->join(new Client());

        $membership = $channel->join(new Client());

        $this->assertFalse($membership->has(MembershipMode::Operator));
    }

    #[Test]
    public function joining_the_same_client_is_idempotent(): void
    {
        $channel = new Channel('#php');
        $client = new Client();

        $first = $channel->join($client);
        $second = $channel->join($client);

        $this->assertSame($first, $second);
        $this->assertSame([$first], $channel->members());
    }

    #[Test]
    public function it_finds_membership_by_client_identity(): void
    {
        $channel = new Channel('#php');
        $member = new Client();
        $stranger = new Client();
        $membership = $channel->join($member);

        $this->assertTrue($channel->hasMember($member));
        $this->assertSame($membership, $channel->membershipFor($member));
        $this->assertFalse($channel->hasMember($stranger));
        $this->assertNull($channel->membershipFor($stranger));
    }

    #[Test]
    public function it_returns_memberships_as_an_ordered_list(): void
    {
        $channel = new Channel('#php');
        $first = $channel->join(new Client());
        $second = $channel->join(new Client());

        $this->assertSame([$first, $second], $channel->members());
    }

    #[Test]
    public function it_counts_its_current_members(): void
    {
        $channel = new Channel('#php');
        $first = new Client();

        $this->assertSame(0, $channel->memberCount());

        $channel->join($first);
        $channel->join(new Client());

        $this->assertSame(2, $channel->memberCount());

        $channel->leave($first);

        $this->assertSame(1, $channel->memberCount());
    }

    #[Test]
    public function it_starts_with_no_external_messages_and_protected_topic_modes(): void
    {
        $channel = new Channel('#php');

        $this->assertSame(
            [ChannelMode::NoExternalMessages, ChannelMode::ProtectedTopic],
            $channel->modes(),
        );
        $this->assertTrue($channel->hasMode(ChannelMode::NoExternalMessages));
        $this->assertTrue($channel->hasMode(ChannelMode::ProtectedTopic));
        $this->assertFalse($channel->hasMode(ChannelMode::Moderated));
    }

    #[Test]
    public function enabling_and_disabling_modes_is_idempotent(): void
    {
        $channel = new Channel('#php');

        $this->assertTrue($channel->enableMode(ChannelMode::Moderated));
        $this->assertFalse($channel->enableMode(ChannelMode::Moderated));
        $this->assertTrue($channel->hasMode(ChannelMode::Moderated));
        $this->assertTrue($channel->disableMode(ChannelMode::Moderated));
        $this->assertFalse($channel->disableMode(ChannelMode::Moderated));
        $this->assertFalse($channel->hasMode(ChannelMode::Moderated));
    }

    #[Test]
    public function no_external_messages_mode_controls_whether_outsiders_can_send(): void
    {
        $channel = new Channel('#php');
        $outsider = new Client();

        $this->assertFalse($channel->canSendMessage($outsider));

        $channel->disableMode(ChannelMode::NoExternalMessages);

        $this->assertTrue($channel->canSendMessage($outsider));
    }

    #[Test]
    public function moderated_mode_only_allows_operators_and_voiced_members_to_send(): void
    {
        $channel = new Channel('#php');
        $operator = new Client();
        $member = new Client();
        $channel->join($operator);
        $membership = $channel->join($member);
        $channel->enableMode(ChannelMode::Moderated);

        $this->assertTrue($channel->canSendMessage($operator));
        $this->assertFalse($channel->canSendMessage($member));

        $membership->grant(MembershipMode::Voice);

        $this->assertTrue($channel->canSendMessage($member));
    }

    #[Test]
    public function it_starts_without_a_topic(): void
    {
        $this->assertNull(new Channel('#php')->topic);
    }

    #[Test]
    public function it_sets_its_topic(): void
    {
        $channel = new Channel('#php');
        $channel->setTopic('PHP discussion', 'Jane');
        $topic = $channel->topic;

        $this->assertNotNull($topic);
        $this->assertSame('PHP discussion', $topic->text);
        $this->assertSame('Jane', $topic->setBy);
    }

    #[Test]
    public function it_clears_its_topic(): void
    {
        $channel = new Channel('#php');
        $channel->setTopic('PHP discussion', 'Jane');
        $channel->clearTopic();

        $this->assertNull($channel->topic);
    }

    #[Test]
    public function it_removes_a_member(): void
    {
        $channel = new Channel('#php');
        $client = new Client();
        $channel->join($client);

        $this->assertTrue($channel->leave($client));
        $this->assertFalse($channel->hasMember($client));
        $this->assertFalse($channel->hasMembers());
    }

    #[Test]
    public function it_cannot_remove_a_client_who_is_not_a_member(): void
    {
        $channel = new Channel('#php');

        $this->assertFalse($channel->leave(new Client()));
        $this->assertFalse($channel->hasMembers());
    }
}
