<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessageSizeTest extends TestCase
{
    #[Test]
    public function it_includes_the_terminating_crlf_in_the_size(): void
    {
        $message = new Message(command: 'PING', parameters: ['hello']);

        $this->assertSame(12, $this->messageSize()->inBytes($message));
    }

    #[Test]
    public function it_excludes_tags_from_the_main_section_size(): void
    {
        $message = new Message(
            command: 'PING',
            parameters: ['hello'],
            tags: [new MessageTag('example', str_repeat('x', 100))],
        );

        $this->assertSame(12, $this->messageSize()->inBytes($message));
    }

    #[Test]
    public function it_accepts_a_message_at_the_size_limit(): void
    {
        $message = new Message(command: 'NOTICE', parameters: ['John', str_repeat('x', 498)]);

        $this->assertSame(MessageSize::MAX_BYTES, $this->messageSize()->inBytes($message));
        $this->assertTrue($this->messageSize()->fits($message));
    }

    #[Test]
    public function it_rejects_a_message_over_the_size_limit(): void
    {
        $message = new Message(command: 'NOTICE', parameters: ['John', str_repeat('x', 499)]);

        $this->assertSame(MessageSize::MAX_BYTES + 1, $this->messageSize()->inBytes($message));
        $this->assertFalse($this->messageSize()->fits($message));
    }

    private function messageSize(): MessageSize
    {
        return new MessageSize(new MessageEncoder());
    }
}
