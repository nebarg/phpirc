<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use PhpIrc\Irc\Config\ServerLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServerLimitsTest extends TestCase
{
    #[Test]
    public function it_truncates_server_state_to_its_byte_limits(): void
    {
        $limits = new ServerLimits();

        $this->assertSame(
            str_repeat('t', ServerLimits::MAX_TOPIC_BYTES),
            $limits->truncateTopic(str_repeat('t', ServerLimits::MAX_TOPIC_BYTES + 1)),
        );
        $this->assertSame(
            str_repeat('u', ServerLimits::MAX_USERNAME_BYTES),
            $limits->truncateUsername(str_repeat('u', ServerLimits::MAX_USERNAME_BYTES + 1)),
        );
        $this->assertSame(
            str_repeat('h', ServerLimits::MAX_HOSTNAME_BYTES),
            $limits->truncateHostname(str_repeat('h', ServerLimits::MAX_HOSTNAME_BYTES + 1)),
        );
        $this->assertSame(
            str_repeat('r', ServerLimits::MAX_REAL_NAME_BYTES),
            $limits->truncateRealName(str_repeat('r', ServerLimits::MAX_REAL_NAME_BYTES + 1)),
        );
    }

    #[Test]
    public function it_does_not_split_a_utf8_character_when_truncating(): void
    {
        $topic = str_repeat('t', ServerLimits::MAX_TOPIC_BYTES - 1) . '£';

        $truncated = new ServerLimits()->truncateTopic($topic);

        $this->assertSame(str_repeat('t', ServerLimits::MAX_TOPIC_BYTES - 1), $truncated);
        $this->assertTrue(mb_check_encoding($truncated, 'UTF-8'));
    }

    #[Test]
    public function it_leaves_values_within_the_limit_unchanged(): void
    {
        $limits = new ServerLimits();

        $this->assertSame('A topic', $limits->truncateTopic('A topic'));
        $this->assertSame('john', $limits->truncateUsername('john'));
        $this->assertSame('203.0.113.10', $limits->truncateHostname('203.0.113.10'));
        $this->assertSame('John Doe', $limits->truncateRealName('John Doe'));
    }
}
