<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Mode;

use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
use PhpIrc\Irc\Mode\ModeAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModeChangeParserTest extends TestCase
{
    #[Test]
    public function it_parses_a_single_membership_mode_change(): void
    {
        $result = new ModeChangeParser()->parse('+o', ['Jane']);

        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[0]);
        $this->assertSame(ModeAction::Add, $result->changes[0]->action);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_parses_combined_modes_and_consumes_arguments_sequentially(): void
    {
        $result = new ModeChangeParser()->parse('+ov-v', ['Jane', 'John', 'Fred']);

        $this->assertCount(3, $result->changes);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[0]);
        $this->assertSame(ModeAction::Add, $result->changes[0]->action);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[1]);
        $this->assertSame(ModeAction::Add, $result->changes[1]->action);
        $this->assertSame(MembershipMode::Voice, $result->changes[1]->mode);
        $this->assertSame('John', $result->changes[1]->nickname);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[2]);
        $this->assertSame(ModeAction::Remove, $result->changes[2]->action);
        $this->assertSame(MembershipMode::Voice, $result->changes[2]->mode);
        $this->assertSame('Fred', $result->changes[2]->nickname);
    }

    #[Test]
    public function it_reports_unknown_modes_without_consuming_arguments(): void
    {
        $result = new ModeChangeParser()->parse('+xov', ['Jane', 'John']);

        $this->assertSame(['x'], $result->unknownModes);
        $this->assertCount(2, $result->changes);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[0]);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[1]);
        $this->assertSame('John', $result->changes[1]->nickname);
    }

    #[Test]
    public function it_ignores_recognised_modes_without_arguments(): void
    {
        $result = new ModeChangeParser()->parse('+ov', ['Jane']);

        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[0]);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_ignores_an_empty_argument(): void
    {
        $result = new ModeChangeParser()->parse('+o', ['']);

        $this->assertSame([], $result->changes);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_reports_mode_characters_without_an_action_as_unknown(): void
    {
        $result = new ModeChangeParser()->parse('o+v', ['Jane']);

        $this->assertSame(['o'], $result->unknownModes);
        $this->assertCount(1, $result->changes);
        $this->assertSame(MembershipMode::Voice, $result->changes[0]->mode);
    }

    #[Test]
    public function it_parses_channel_modes_without_consuming_arguments(): void
    {
        $result = new ModeChangeParser()->parse('+mnt', []);

        $this->assertCount(3, $result->changes);

        foreach ($result->changes as $change) {
            $this->assertInstanceOf(ChannelModeChange::class, $change);
            $this->assertSame(ModeAction::Add, $change->action);
        }

        $this->assertSame(ChannelMode::Moderated, $result->changes[0]->mode);
        $this->assertSame(ChannelMode::NoExternalMessages, $result->changes[1]->mode);
        $this->assertSame(ChannelMode::ProtectedTopic, $result->changes[2]->mode);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_preserves_the_order_of_mixed_channel_and_membership_modes(): void
    {
        $result = new ModeChangeParser()->parse('+mov-n', ['Jane', 'John']);

        $this->assertCount(4, $result->changes);
        $this->assertInstanceOf(ChannelModeChange::class, $result->changes[0]);
        $this->assertSame(ChannelMode::Moderated, $result->changes[0]->mode);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[1]);
        $this->assertSame(MembershipMode::Operator, $result->changes[1]->mode);
        $this->assertSame('Jane', $result->changes[1]->nickname);
        $this->assertInstanceOf(MembershipModeChange::class, $result->changes[2]);
        $this->assertSame(MembershipMode::Voice, $result->changes[2]->mode);
        $this->assertSame('John', $result->changes[2]->nickname);
        $this->assertInstanceOf(ChannelModeChange::class, $result->changes[3]);
        $this->assertSame(ModeAction::Remove, $result->changes[3]->action);
        $this->assertSame(ChannelMode::NoExternalMessages, $result->changes[3]->mode);
    }
}
