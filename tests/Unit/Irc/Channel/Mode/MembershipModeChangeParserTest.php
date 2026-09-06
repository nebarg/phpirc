<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Mode;

use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChangeParser;
use PhpIrc\Irc\Mode\ModeAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MembershipModeChangeParserTest extends TestCase
{
    #[Test]
    public function it_parses_a_single_membership_mode_change(): void
    {
        $result = new MembershipModeChangeParser()->parse('+o', ['Jane']);

        $this->assertCount(1, $result->changes);
        $this->assertSame(ModeAction::Add, $result->changes[0]->action);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_parses_combined_modes_and_consumes_arguments_sequentially(): void
    {
        $result = new MembershipModeChangeParser()->parse('+ov-v', ['Jane', 'John', 'Fred']);

        $this->assertCount(3, $result->changes);
        $this->assertSame(ModeAction::Add, $result->changes[0]->action);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame(ModeAction::Add, $result->changes[1]->action);
        $this->assertSame(MembershipMode::Voice, $result->changes[1]->mode);
        $this->assertSame('John', $result->changes[1]->nickname);
        $this->assertSame(ModeAction::Remove, $result->changes[2]->action);
        $this->assertSame(MembershipMode::Voice, $result->changes[2]->mode);
        $this->assertSame('Fred', $result->changes[2]->nickname);
    }

    #[Test]
    public function it_reports_unknown_modes_without_consuming_arguments(): void
    {
        $result = new MembershipModeChangeParser()->parse('+xov', ['Jane', 'John']);

        $this->assertSame(['x'], $result->unknownModes);
        $this->assertCount(2, $result->changes);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame('John', $result->changes[1]->nickname);
    }

    #[Test]
    public function it_ignores_recognised_modes_without_arguments(): void
    {
        $result = new MembershipModeChangeParser()->parse('+ov', ['Jane']);

        $this->assertCount(1, $result->changes);
        $this->assertSame(MembershipMode::Operator, $result->changes[0]->mode);
        $this->assertSame('Jane', $result->changes[0]->nickname);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_ignores_an_empty_argument(): void
    {
        $result = new MembershipModeChangeParser()->parse('+o', ['']);

        $this->assertSame([], $result->changes);
        $this->assertSame([], $result->unknownModes);
    }

    #[Test]
    public function it_reports_mode_characters_without_an_action_as_unknown(): void
    {
        $result = new MembershipModeChangeParser()->parse('o+v', ['Jane']);

        $this->assertSame(['o'], $result->unknownModes);
        $this->assertCount(1, $result->changes);
        $this->assertSame(MembershipMode::Voice, $result->changes[0]->mode);
    }
}
