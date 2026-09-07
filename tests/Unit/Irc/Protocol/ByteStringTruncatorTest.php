<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use InvalidArgumentException;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ByteStringTruncatorTest extends TestCase
{
    #[Test]
    public function it_truncates_a_string_to_the_byte_limit(): void
    {
        $this->assertSame('abcd', new ByteStringTruncator()->truncate('abcdef', 4));
    }

    #[Test]
    public function it_does_not_split_a_utf8_character(): void
    {
        $truncated = new ByteStringTruncator()->truncate('abc£', 4);

        $this->assertSame('abc', $truncated);
        $this->assertTrue(mb_check_encoding($truncated, 'UTF-8'));
    }

    #[Test]
    public function it_leaves_a_string_within_the_limit_unchanged(): void
    {
        $this->assertSame('abcdef', new ByteStringTruncator()->truncate('abcdef', 6));
    }

    #[Test]
    public function it_falls_back_to_byte_truncation_for_non_utf8_input(): void
    {
        $this->assertSame("\xffa", new ByteStringTruncator()->truncate("\xffabc", 2));
    }

    #[Test]
    public function it_rejects_a_negative_byte_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum bytes cannot be negative.');

        new ByteStringTruncator()->truncate('value', -1);
    }
}
