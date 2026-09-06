<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Mode;

use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MembershipModeTest extends TestCase
{
    #[Test]
    public function it_defines_the_standard_mode_characters_and_prefixes(): void
    {
        $this->assertSame('o', MembershipMode::Operator->value);
        $this->assertSame('@', MembershipMode::Operator->prefix());
        $this->assertSame('v', MembershipMode::Voice->value);
        $this->assertSame('+', MembershipMode::Voice->prefix());
    }

    #[Test]
    public function operator_has_the_higher_display_rank(): void
    {
        $this->assertGreaterThan(
            MembershipMode::Voice->prefixRank(),
            MembershipMode::Operator->prefixRank(),
        );
    }
}
