<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTag;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessageTextLimiterTest extends TestCase
{
    #[Test]
    public function it_returns_the_same_message_when_it_already_fits(): void
    {
        $message = new Message(
            command: 'PRIVMSG',
            parameters: ['#php', 'Hello channel'],
            source: 'John',
        );

        $this->assertSame($message, $this->limiter()->limit($message));
    }

    #[Test]
    public function it_truncates_only_the_final_text_parameter_to_fit(): void
    {
        $message = new Message(
            command: 'NOTICE',
            parameters: ['Jane', str_repeat('Long message ', 50)],
            source: 'John',
            tags: [new MessageTag('example', 'value')],
        );

        $limited = $this->limiter()->limit($message);

        $this->assertSame(MessageSize::MAX_BYTES, $this->messageSize()->inBytes($limited));
        $this->assertSame($message->command, $limited->command);
        $this->assertSame($message->source, $limited->source);
        $this->assertSame($message->tags, $limited->tags);
        $this->assertSame($message->parameter(0), $limited->parameter(0));
        $this->assertLessThan(strlen($message->parameter(1)), strlen($limited->parameter(1)));
    }

    #[Test]
    public function it_does_not_split_a_utf8_character_when_limiting_text(): void
    {
        $messageSize = $this->messageSize();
        $fixedMessage = new Message(
            command: 'PRIVMSG',
            parameters: ['#php', 'x'],
            source: 'John',
        );
        $availableTextBytes = MessageSize::MAX_BYTES - ($messageSize->inBytes($fixedMessage) - 1);
        $prefixBytes = max(0, $availableTextBytes - 1);
        $message = new Message(
            command: 'PRIVMSG',
            parameters: ['#php', str_repeat('a', $prefixBytes) . '£'],
            source: 'John',
        );

        $limited = $this->limiter()->limit($message);

        $this->assertSame(str_repeat('a', $prefixBytes), $limited->parameter(1));
        $this->assertTrue(mb_check_encoding($limited->parameter(1), 'UTF-8'));
        $this->assertTrue($messageSize->fits($limited));
    }

    #[Test]
    public function it_rejects_an_oversized_message_without_a_text_parameter(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('Oversized message does not have a text parameter to truncate.');

        $this->limiter()->limit(new Message(
            command: 'PING',
            source: str_repeat('s', MessageSize::MAX_BYTES),
        ));
    }

    #[Test]
    public function it_rejects_a_message_whose_fixed_fields_exceed_the_limit(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('Message fields exceed the IRC message-size limit.');

        $this->limiter()->limit(new Message(
            command: 'PRIVMSG',
            parameters: [str_repeat('t', MessageSize::MAX_BYTES), 'Hello'],
            source: 'John',
        ));
    }

    private function limiter(): MessageTextLimiter
    {
        return new MessageTextLimiter($this->messageSize(), new ByteStringTruncator());
    }

    private function messageSize(): MessageSize
    {
        return new MessageSize(new MessageEncoder());
    }
}
