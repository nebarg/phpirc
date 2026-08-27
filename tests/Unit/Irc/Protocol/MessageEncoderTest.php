<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessageEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{message: Message, expected: string}>
     */
    public static function validTags(): iterable
    {
        yield 'no tags' => [
            'message' => new Message([], null, 'TESTCMD', []),
            'expected' => 'TESTCMD' . "\r\n",
        ];
        yield 'tag without a value' => [
            'message' => new Message([new MessageTag('aaa', null)], null, 'TESTCMD', []),
            'expected' => '@aaa TESTCMD' . "\r\n",
        ];
        yield 'tag with an empty value' => [
            'message' => new Message([new MessageTag('empty', '')], null, 'TESTCMD', []),
            'expected' => '@empty TESTCMD' . "\r\n",
        ];
        yield 'tag with a value' => [
            'message' => new Message([new MessageTag('aaa', 'bbb')], null, 'TESTCMD', []),
            'expected' => '@aaa=bbb TESTCMD' . "\r\n",
        ];
        yield 'tag with a backslash' => [
            'message' => new Message([new MessageTag('slash', 'a\\b')], null, 'TESTCMD', []),
            'expected' => '@slash=a\\\\b TESTCMD' . "\r\n",
        ];
        yield 'tag with a semicolon' => [
            'message' => new Message([new MessageTag('semi', 'a;b')], null, 'TESTCMD', []),
            'expected' => '@semi=a\:b TESTCMD' . "\r\n",
        ];
        yield 'tag with a space' => [
            'message' => new Message([new MessageTag('space', 'hello world')], null, 'TESTCMD', []),
            'expected' => '@space=hello\sworld TESTCMD' . "\r\n",
        ];
        yield 'tag with a carriage return' => [
            'message' => new Message([new MessageTag('return', "hello\rworld")], null, 'TESTCMD', []),
            'expected' => '@return=hello\rworld TESTCMD' . "\r\n",
        ];
        yield 'tag with a line feed' => [
            'message' => new Message([new MessageTag('newline', "hello\nworld")], null, 'TESTCMD', []),
            'expected' => '@newline=hello\nworld TESTCMD' . "\r\n",
        ];
        yield 'multiple tags' => [
            'message' => new Message(
                [
                    new MessageTag('aaa', 'bbb'),
                    new MessageTag('ccc', null),
                ],
                null,
                'TESTCMD',
                [],
            ),
            'expected' => '@aaa=bbb;ccc TESTCMD' . "\r\n",
        ];
    }

    #[Test]
    #[DataProvider('validTags')]
    public function it_correctly_encodes_tags(Message $message, string $expected): void
    {
        $this->assertSame(
            $expected,
            new MessageEncoder()->encode($message),
        );
    }

    #[Test]
    public function it_correctly_encodes_a_source_with_and_without_tags(): void
    {
        $encoder = new MessageEncoder();

        $this->assertSame(
            ':nick!user@host TESTCMD' . "\r\n",
            $encoder->encode(new Message([], 'nick!user@host', 'TESTCMD', [])),
        );
        $this->assertSame(
            '@aaa=bbb :nick!user@host TESTCMD' . "\r\n",
            $encoder->encode(new Message(
                [new MessageTag('aaa', 'bbb')],
                'nick!user@host',
                'TESTCMD',
                [],
            )),
        );
    }

    #[Test]
    public function it_normalizes_a_lowercase_command_to_uppercase(): void
    {
        $this->assertSame(
            'PRIVMSG' . "\r\n",
            new MessageEncoder()->encode(new Message([], null, 'privmsg', [])),
        );
    }

    /**
     * @return iterable<string, array{parameters: list<string>, expected: string}>
     */
    public static function validParameters(): iterable
    {
        yield 'single parameter' => [
            'parameters' => ['Grant'],
            'expected' => 'TESTCMD Grant' . "\r\n",
        ];
        yield 'multiple parameters' => [
            'parameters' => ['#php', 'Hello'],
            'expected' => 'TESTCMD #php Hello' . "\r\n",
        ];
        yield 'empty trailing parameter' => [
            'parameters' => ['#php', ''],
            'expected' => 'TESTCMD #php :' . "\r\n",
        ];
        yield 'trailing parameter containing spaces' => [
            'parameters' => ['#php', 'Hello everyone'],
            'expected' => 'TESTCMD #php :Hello everyone' . "\r\n",
        ];
        yield 'colon within a middle parameter' => [
            'parameters' => ['abc:def', 'value'],
            'expected' => 'TESTCMD abc:def value' . "\r\n",
        ];
        yield 'trailing parameter beginning with a colon' => [
            'parameters' => ['abc:def', ':value'],
            'expected' => 'TESTCMD abc:def ::value' . "\r\n",
        ];
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('validParameters')]
    public function it_correctly_encodes_parameters(array $parameters, string $expected): void
    {
        $this->assertSame(
            $expected,
            new MessageEncoder()->encode(new Message([], null, 'TESTCMD', $parameters)),
        );
    }

    /**
     * @return iterable<string, array{message: Message}>
     */
    public static function InvalidMessageExceptions(): iterable
    {
        yield 'empty source' => [
            'message' => new Message([], '', 'TESTCMD', []),
        ];
        yield 'null byte in source' => [
            'message' => new Message([], "nick\0name", 'TESTCMD', []),
        ];
        yield 'carriage return in source' => [
            'message' => new Message([], "nick\rname", 'TESTCMD', []),
        ];
        yield 'line feed in source' => [
            'message' => new Message([], "nick\nname", 'TESTCMD', []),
        ];
        yield 'space in source' => [
            'message' => new Message([], 'nick name', 'TESTCMD', []),
        ];
        yield 'empty command' => [
            'message' => new Message([], null, '', []),
        ];
        yield 'null byte in command' => [
            'message' => new Message([], null, "TEST\0CMD", []),
        ];
        yield 'carriage return in command' => [
            'message' => new Message([], null, "TEST\rCMD", []),
        ];
        yield 'line feed in command' => [
            'message' => new Message([], null, "TEST\nCMD", []),
        ];
        yield 'space in command' => [
            'message' => new Message([], null, 'TEST CMD', []),
        ];
        yield 'short numeric command' => [
            'message' => new Message([], null, '12', []),
        ];
        yield 'long numeric command' => [
            'message' => new Message([], null, '0001', []),
        ];
        yield 'alphanumeric command' => [
            'message' => new Message([], null, 'PRIVMSG1', []),
        ];
        yield 'null byte in parameter' => [
            'message' => new Message([], null, 'TESTCMD', ["bad\0parameter"]),
        ];
        yield 'carriage return in parameter' => [
            'message' => new Message([], null, 'TESTCMD', ["bad\rparameter"]),
        ];
        yield 'line feed in parameter' => [
            'message' => new Message([], null, 'TESTCMD', ["bad\nparameter"]),
        ];
        yield 'empty middle parameter' => [
            'message' => new Message([], null, 'TESTCMD', ['', 'something']),
        ];
        yield 'middle parameter beginning with a colon' => [
            'message' => new Message([], null, 'TESTCMD', [':value', 'something']),
        ];
        yield 'middle parameter containing spaces' => [
            'message' => new Message([], null, 'TESTCMD', ['hello world', 'something']),
        ];
    }

    #[Test]
    #[DataProvider('InvalidMessageExceptions')]
    public function it_rejects_invalid_messages(Message $message): void
    {
        $this->expectException(InvalidMessageException::class);

        new MessageEncoder()->encode($message);
    }
}
