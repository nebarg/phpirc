<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use Override;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLong;
use PhpIrc\Irc\Transport\LineBuffer;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class LineBufferTest extends IntegrationTestCase
{
    private const int MAX_INCOMPLETE_BYTES = ClientMessageSizeValidator::MAX_TAG_BYTES
        + ClientMessageSizeValidator::MAX_MAIN_BYTES
        + 3;

    private LineBuffer $buffer;

    #[Override]
    protected function setUp(): void
    {
        $this->buffer = new LineBuffer(new ClientMessageSizeValidator);
    }

    #[Test]
    public function it_returns_nothing_until_a_complete_line_is_received(): void
    {
        $this->assertSame([], $this->buffer->push('PRIVMSG #php :hel'));
    }

    #[Test]
    public function it_completes_a_line_across_multiple_pushes(): void
    {
        $this->assertSame([], $this->buffer->push('PRIVMSG #php :hel'));
        $this->assertSame(
            ['PRIVMSG #php :hello'],
            $this->buffer->push("lo\r\n"),
        );
    }

    #[Test]
    public function it_handles_a_crlf_delimiter_split_across_pushes(): void
    {
        $this->assertSame([], $this->buffer->push("PING :token\r"));
        $this->assertSame(['PING :token'], $this->buffer->push("\n"));
    }

    #[Test]
    public function it_extracts_multiple_lines_from_one_push(): void
    {
        $this->assertSame(
            ['PING :one', 'PING :two'],
            $this->buffer->push("PING :one\r\nPING :two\r\n"),
        );
    }

    #[Test]
    public function it_retains_an_incomplete_remainder_after_complete_lines(): void
    {
        $this->assertSame(
            ['PING :one'],
            $this->buffer->push("PING :one\r\nPRIV"),
        );

        $this->assertSame(
            ['PRIVMSG #php :hello'],
            $this->buffer->push("MSG #php :hello\r\n"),
        );
    }

    #[Test]
    public function it_ignores_empty_lines_without_blocking_following_lines(): void
    {
        $this->assertSame(
            ['PING :token'],
            $this->buffer->push("\r\nPING :token\r\n"),
        );
    }

    #[Test]
    public function it_only_discards_lines_that_are_actually_empty(): void
    {
        $this->assertSame(
            ['0'],
            $this->buffer->push("0\r\n\r\n"),
        );
    }

    #[Test]
    public function it_preserves_line_contents_exactly(): void
    {
        $line = 'PRIVMSG   #php   :hello  there ';

        $this->assertSame([$line], $this->buffer->push($line . "\r\n"));
    }

    #[Test]
    public function it_does_not_treat_a_lone_line_feed_as_a_delimiter(): void
    {
        $this->assertSame([], $this->buffer->push("PING :one\n"));
        $this->assertSame(["PING :one\n"], $this->buffer->push("\r\n"));
    }

    #[Test]
    public function it_does_not_treat_a_lone_carriage_return_as_a_delimiter(): void
    {
        $this->assertSame([], $this->buffer->push("PING :one\rX"));
        $this->assertSame(["PING :one\rX"], $this->buffer->push("\r\n"));
    }

    #[Test]
    public function it_validates_each_completed_line(): void
    {
        $this->expectException(InputTooLong::class);

        $this->buffer->push(
            str_repeat('a', ClientMessageSizeValidator::MAX_MAIN_BYTES + 1) . "\r\n",
        );
    }

    #[Test]
    public function it_rejects_an_incomplete_line_above_the_absolute_buffer_limit(): void
    {
        $this->assertSame(
            [],
            $this->buffer->push(str_repeat('a', self::MAX_INCOMPLETE_BYTES)),
        );

        $this->expectException(InputTooLong::class);

        $this->buffer->push('a');
    }

    #[Test]
    public function it_accepts_a_large_chunk_containing_individually_valid_lines(): void
    {
        $line = 'PING :token';
        $numberOfLines = 400;

        $this->assertSame(
            array_fill(0, $numberOfLines, $line),
            $this->buffer->push(str_repeat($line . "\r\n", $numberOfLines)),
        );
    }
}
