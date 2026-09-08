<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use InvalidArgumentException;
use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Config\ServerLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MotdTest extends TestCase
{
    #[Test]
    public function it_is_empty_by_default(): void
    {
        $motd = new Motd();

        $this->assertTrue($motd->isEmpty());
        $this->assertSame([], $motd->lines);
    }

    #[Test]
    public function it_accepts_message_lines_including_blank_lines(): void
    {
        $motd = new Motd(['Welcome', '', 'Have fun']);

        $this->assertFalse($motd->isEmpty());
        $this->assertSame(['Welcome', '', 'Have fun'], $motd->lines);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCharacters(): iterable
    {
        yield 'NUL' => ["invalid\0line"];
        yield 'carriage return' => ["invalid\rline"];
        yield 'line feed' => ["invalid\nline"];
    }

    #[Test]
    #[DataProvider('invalidCharacters')]
    public function it_rejects_protocol_delimiters_inside_a_line(string $line): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MOTD lines cannot contain NUL, carriage-return or line-feed characters.');

        new Motd([$line]);
    }

    #[Test]
    public function it_rejects_lines_that_cannot_fit_in_an_irc_message(): void
    {
        $maximumBytes = ServerLimits::MAX_MOTD_LINE_BYTES;
        assert($maximumBytes > 0, 'MOTD line limit must be positive.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("MOTD lines cannot exceed {$maximumBytes} bytes.");

        new Motd([str_repeat('x', $maximumBytes + 1)]);
    }
}
