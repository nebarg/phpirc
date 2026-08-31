<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLongException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\LineBuffer;
use PhpIrc\Irc\Transport\MessageCodec;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessageCodecTest extends TestCase
{
    #[Test]
    public function it_decodes_complete_messages_in_order(): void
    {
        $messages = $this->decode(
            $this->codec(),
            "PING :one\r\nPONG :two\r\n",
        );

        $this->assertSame(['PING', 'PONG'], array_column($messages, 'command'));
        $this->assertSame(['one'], $messages[0]->parameters);
        $this->assertSame(['two'], $messages[1]->parameters);
    }

    #[Test]
    public function it_buffers_an_incomplete_message_between_decodes(): void
    {
        $codec = $this->codec();

        $first = $this->decode($codec, 'PRIVMSG #php :hel');
        $second = $this->decode($codec, "lo\r\n");

        $this->assertSame([], $first);
        $this->assertCount(1, $second);
        $this->assertSame('PRIVMSG', $second[0]->command);
        $this->assertSame(['#php', 'hello'], $second[0]->parameters);
    }

    #[Test]
    public function it_ignores_an_invalid_message_and_decodes_the_next_one(): void
    {
        $messages = $this->decode(
            $this->codec(),
            "PONG: invalid\r\nPING :token\r\n",
        );

        $this->assertCount(1, $messages);
        $this->assertSame('PING', $messages[0]->command);
        $this->assertSame(['token'], $messages[0]->parameters);
    }

    #[Test]
    public function it_propagates_message_size_errors(): void
    {
        $this->expectException(InputTooLongException::class);

        $this->decode(
            $this->codec(),
            str_repeat('a', ClientMessageSizeValidator::MAX_MAIN_BYTES + 1) . "\r\n",
        );
    }

    #[Test]
    public function it_encodes_a_message(): void
    {
        $encoded = $this->codec()->encode(new Message(
            command: 'privmsg',
            parameters: ['#php', 'hello there'],
        ));

        $this->assertSame("PRIVMSG #php :hello there\r\n", $encoded);
    }

    /** @return list<Message> */
    private function decode(MessageCodec $codec, string $bytes): array
    {
        $messages = [];

        foreach ($codec->decode($bytes) as $message) {
            $messages[] = $message;
        }

        return $messages;
    }

    private function codec(): MessageCodec
    {
        return new MessageCodec(
            buffer: new LineBuffer(new ClientMessageSizeValidator()),
            parser: new MessageParser(),
            encoder: new MessageEncoder(),
        );
    }
}
