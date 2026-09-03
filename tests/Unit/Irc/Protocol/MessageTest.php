<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessageTest extends TestCase
{
    #[Test]
    public function it_returns_a_parameter_by_position(): void
    {
        $message = new Message(
            command: 'PRIVMSG',
            parameters: ['#php', 'Hello'],
        );

        $this->assertSame('#php', $message->parameter(0));
        $this->assertSame('Hello', $message->parameter(1));
    }

    #[Test]
    public function it_returns_an_empty_string_for_a_missing_parameter(): void
    {
        $message = new Message(command: 'PING');

        $this->assertSame('', $message->parameter(0));
    }

    #[Test]
    public function it_returns_null_for_a_missing_optional_parameter(): void
    {
        $message = new Message(command: 'PING');

        $this->assertNull($message->optionalParameter(0));
    }

    #[Test]
    public function it_preserves_an_explicitly_empty_parameter(): void
    {
        $message = new Message(
            command: 'PRIVMSG',
            parameters: [''],
        );

        $this->assertSame('', $message->parameter(0));
        $this->assertSame('', $message->optionalParameter(0));
    }

    #[Test]
    public function it_identifies_a_missing_parameter(): void
    {
        $missing = new Message(command: 'PRIVMSG');
        $empty = new Message(command: 'PRIVMSG', parameters: ['']);
        $present = new Message(command: 'PRIVMSG', parameters: ['#php']);

        $this->assertTrue($missing->isParameterMissing(0));
        $this->assertFalse($empty->isParameterMissing(0));
        $this->assertFalse($present->isParameterMissing(0));
    }

    #[Test]
    public function it_identifies_a_provided_empty_parameter(): void
    {
        $missing = new Message(command: 'PRIVMSG');
        $empty = new Message(command: 'PRIVMSG', parameters: ['']);
        $present = new Message(command: 'PRIVMSG', parameters: ['#php']);

        $this->assertFalse($missing->isParameterEmpty(0));
        $this->assertTrue($empty->isParameterEmpty(0));
        $this->assertFalse($present->isParameterEmpty(0));
    }

    #[Test]
    public function it_identifies_a_missing_or_empty_parameter(): void
    {
        $missing = new Message(command: 'PRIVMSG');
        $empty = new Message(command: 'PRIVMSG', parameters: ['']);
        $present = new Message(command: 'PRIVMSG', parameters: ['#php']);

        $this->assertTrue($missing->isParameterMissingOrEmpty(0));
        $this->assertTrue($empty->isParameterMissingOrEmpty(0));
        $this->assertFalse($present->isParameterMissingOrEmpty(0));
    }

    #[Test]
    public function it_treats_whitespace_as_parameter_content(): void
    {
        $message = new Message(command: 'PRIVMSG', parameters: [' ']);

        $this->assertFalse($message->isParameterMissing(0));
        $this->assertFalse($message->isParameterEmpty(0));
        $this->assertFalse($message->isParameterMissingOrEmpty(0));
    }
}
