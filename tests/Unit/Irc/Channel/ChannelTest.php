<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
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
    public function it_makes_the_first_member_an_operator(): void
    {
        $channel = new Channel('#php');

        $membership = $channel->join(new Client());

        $this->assertTrue($membership->isOperator);
    }

    #[Test]
    public function it_does_not_make_later_members_operators(): void
    {
        $channel = new Channel('#php');
        $channel->join(new Client());

        $membership = $channel->join(new Client());

        $this->assertFalse($membership->isOperator);
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

        $this->assertTrue($channel->has($member));
        $this->assertSame($membership, $channel->membershipFor($member));
        $this->assertFalse($channel->has($stranger));
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
        $this->assertFalse($channel->has($client));
        $this->assertTrue($channel->isEmpty());
    }

    #[Test]
    public function it_cannot_remove_a_client_who_is_not_a_member(): void
    {
        $channel = new Channel('#php');

        $this->assertFalse($channel->leave(new Client()));
        $this->assertTrue($channel->isEmpty());
    }
}
