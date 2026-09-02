<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelRegistryTest extends TestCase
{
    #[Test]
    public function it_creates_and_finds_a_channel_case_insensitively(): void
    {
        $registry = $this->registry();

        $channel = $registry->join('#PHP', new Client());

        $this->assertSame('#PHP', $channel->name);
        $this->assertSame($channel, $registry->find('#php'));
        $this->assertSame($channel, $registry->find('#PHP'));
    }

    #[Test]
    public function it_joins_clients_to_an_existing_channel(): void
    {
        $registry = $this->registry();
        $first = new Client();
        $second = new Client();

        $channel = $registry->join('#php', $first);
        $sameChannel = $registry->join('#PHP', $second);

        $this->assertSame($channel, $sameChannel);
        $this->assertTrue($channel->has($first));
        $this->assertTrue($channel->has($second));
        $this->assertCount(2, $channel->memberships());
    }

    #[Test]
    public function it_finds_every_channel_joined_by_a_client(): void
    {
        $registry = $this->registry();
        $client = new Client();
        $other = new Client();
        $first = $registry->join('#one', $client);
        $second = $registry->join('#two', $client);
        $registry->join('#other', $other);

        $this->assertSame([$first, $second], $registry->channelsFor($client));
        $this->assertSame([], $registry->channelsFor(new Client()));
    }

    #[Test]
    public function it_treats_rfc1459_specific_equivalents_as_distinct(): void
    {
        $registry = $this->registry();

        $square = $registry->join('#[php]', new Client());
        $curly = $registry->join('#{PHP}', new Client());

        $this->assertNotSame($square, $curly);
        $this->assertSame($square, $registry->find('#[PHP]'));
        $this->assertSame($curly, $registry->find('#{php}'));
    }

    #[Test]
    public function it_removes_a_member_but_preserves_a_non_empty_channel(): void
    {
        $registry = $this->registry();
        $first = new Client();
        $second = new Client();
        $channel = $registry->join('#php', $first);
        $registry->join('#php', $second);

        $left = $registry->leave($channel, $first);

        $this->assertTrue($left);
        $this->assertFalse($channel->has($first));
        $this->assertTrue($channel->has($second));
        $this->assertSame($channel, $registry->find('#php'));
    }

    #[Test]
    public function it_removes_a_channel_when_its_final_member_leaves(): void
    {
        $registry = $this->registry();
        $client = new Client();
        $channel = $registry->join('#php', $client);

        $left = $registry->leave($channel, $client);

        $this->assertTrue($left);
        $this->assertNull($registry->find('#php'));
    }

    #[Test]
    public function it_does_not_modify_a_different_channel_instance(): void
    {
        $registry = $this->registry();
        $client = new Client();
        $registered = $registry->join('#php', $client);

        $left = $registry->leave(new Channel('#PHP'), $client);

        $this->assertFalse($left);
        $this->assertTrue($registered->has($client));
        $this->assertSame($registered, $registry->find('#php'));
    }

    #[Test]
    public function it_cleans_up_an_empty_registered_channel_even_if_the_client_was_not_removed(): void
    {
        $registry = $this->registry();
        $member = new Client();
        $channel = $registry->join('#php', $member);
        $channel->leave($member);

        $left = $registry->leave($channel, new Client());

        $this->assertFalse($left);
        $this->assertNull($registry->find('#php'));
    }

    #[Test]
    public function it_removes_a_client_from_every_channel(): void
    {
        $registry = $this->registry();
        $client = new Client();
        $other = new Client();
        $emptyAfterLeaving = $registry->join('#one', $client);
        $stillOccupied = $registry->join('#two', $client);
        $registry->join('#two', $other);

        $registry->leaveAll($client);

        $this->assertNull($registry->find('#one'));
        $this->assertFalse($emptyAfterLeaving->has($client));
        $this->assertFalse($stillOccupied->has($client));
        $this->assertTrue($stillOccupied->has($other));
        $this->assertSame($stillOccupied, $registry->find('#two'));
    }

    private function registry(): ChannelRegistry
    {
        return new ChannelRegistry(new AsciiCaseMapper());
    }
}
