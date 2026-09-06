<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MembershipTest extends TestCase
{
    #[Test]
    public function it_starts_without_modes_or_a_prefix(): void
    {
        $membership = new Membership(new Client());

        $this->assertFalse($membership->has(MembershipMode::Operator));
        $this->assertFalse($membership->has(MembershipMode::Voice));
        $this->assertSame('', $membership->highestPrefix());
    }

    #[Test]
    public function it_grants_a_mode_idempotently(): void
    {
        $membership = new Membership(new Client());

        $this->assertTrue($membership->grant(MembershipMode::Voice));
        $this->assertFalse($membership->grant(MembershipMode::Voice));
        $this->assertTrue($membership->has(MembershipMode::Voice));
        $this->assertSame('+', $membership->highestPrefix());
    }

    #[Test]
    public function it_revokes_a_mode_idempotently(): void
    {
        $membership = new Membership(new Client());
        $membership->grant(MembershipMode::Operator);

        $this->assertTrue($membership->revoke(MembershipMode::Operator));
        $this->assertFalse($membership->revoke(MembershipMode::Operator));
        $this->assertFalse($membership->has(MembershipMode::Operator));
        $this->assertSame('', $membership->highestPrefix());
    }

    #[Test]
    public function it_displays_only_the_highest_prefix_when_multiple_modes_are_present(): void
    {
        $membership = new Membership(new Client());
        $membership->grant(MembershipMode::Voice);
        $membership->grant(MembershipMode::Operator);

        $this->assertTrue($membership->has(MembershipMode::Voice));
        $this->assertTrue($membership->has(MembershipMode::Operator));
        $this->assertSame('@', $membership->highestPrefix());
    }
}
