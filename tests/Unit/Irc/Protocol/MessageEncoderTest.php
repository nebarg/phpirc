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
            'message' => new Message(command: 'TESTCMD'),
            'expected' => 'TESTCMD' . "\r\n",
        ];
        yield 'tag without a value' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('aaa', null)]),
            'expected' => '@aaa TESTCMD' . "\r\n",
        ];
        yield 'tag with an empty value' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('empty', '')]),
            'expected' => '@empty TESTCMD' . "\r\n",
        ];
        yield 'tag with a value' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('aaa', 'bbb')]),
            'expected' => '@aaa=bbb TESTCMD' . "\r\n",
        ];
        yield 'tag with a backslash' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('slash', 'a\\b')]),
            'expected' => '@slash=a\\\\b TESTCMD' . "\r\n",
        ];
        yield 'tag with a semicolon' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('semi', 'a;b')]),
            'expected' => '@semi=a\:b TESTCMD' . "\r\n",
        ];
        yield 'tag with a space' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('space', 'hello world')]),
            'expected' => '@space=hello\sworld TESTCMD' . "\r\n",
        ];
        yield 'tag with a carriage return' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('return', "hello\rworld")]),
            'expected' => '@return=hello\rworld TESTCMD' . "\r\n",
        ];
        yield 'tag with a line feed' => [
            'message' => new Message(command: 'TESTCMD', tags: [new MessageTag('newline', "hello\nworld")]),
            'expected' => '@newline=hello\nworld TESTCMD' . "\r\n",
        ];
        yield 'multiple tags' => [
            'message' => new Message(
                command: 'TESTCMD',
                tags: [
                    new MessageTag('aaa', 'bbb'),
                    new MessageTag('ccc', null),
                ],
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
            $encoder->encode(new Message(command: 'TESTCMD', source: 'nick!user@host')),
        );
        $this->assertSame(
            '@aaa=bbb :nick!user@host TESTCMD' . "\r\n",
            $encoder->encode(new Message(
                command: 'TESTCMD',
                source: 'nick!user@host',
                tags: [new MessageTag('aaa', 'bbb')],
            )),
        );
    }

    #[Test]
    public function it_normalizes_a_lowercase_command_to_uppercase(): void
    {
        $this->assertSame(
            'PRIVMSG' . "\r\n",
            new MessageEncoder()->encode(new Message(command: 'privmsg')),
        );
    }

    /**
     * @return iterable<string, array{parameters: list<string>, expected: string}>
     */
    public static function validParameters(): iterable
    {
        yield 'single parameter' => [
            'parameters' => ['John'],
            'expected' => 'TESTCMD John' . "\r\n",
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
            new MessageEncoder()->encode(new Message(command: 'TESTCMD', parameters: $parameters)),
        );
    }

    /**
     * @return iterable<string, array{message: Message}>
     */
    public static function InvalidMessageExceptions(): iterable
    {
        yield 'empty source' => [
            'message' => new Message(command: 'TESTCMD', source: ''),
        ];
        yield 'null byte in source' => [
            'message' => new Message(command: 'TESTCMD', source: "nick\0name"),
        ];
        yield 'carriage return in source' => [
            'message' => new Message(command: 'TESTCMD', source: "nick\rname"),
        ];
        yield 'line feed in source' => [
            'message' => new Message(command: 'TESTCMD', source: "nick\nname"),
        ];
        yield 'space in source' => [
            'message' => new Message(command: 'TESTCMD', source: 'nick name'),
        ];
        yield 'empty command' => [
            'message' => new Message(command: ''),
        ];
        yield 'null byte in command' => [
            'message' => new Message(command: "TEST\0CMD"),
        ];
        yield 'carriage return in command' => [
            'message' => new Message(command: "TEST\rCMD"),
        ];
        yield 'line feed in command' => [
            'message' => new Message(command: "TEST\nCMD"),
        ];
        yield 'space in command' => [
            'message' => new Message(command: 'TEST CMD'),
        ];
        yield 'short numeric command' => [
            'message' => new Message(command: '12'),
        ];
        yield 'long numeric command' => [
            'message' => new Message(command: '0001'),
        ];
        yield 'alphanumeric command' => [
            'message' => new Message(command: 'PRIVMSG1'),
        ];
        yield 'null byte in parameter' => [
            'message' => new Message(command: 'TESTCMD', parameters: ["bad\0parameter"]),
        ];
        yield 'carriage return in parameter' => [
            'message' => new Message(command: 'TESTCMD', parameters: ["bad\rparameter"]),
        ];
        yield 'line feed in parameter' => [
            'message' => new Message(command: 'TESTCMD', parameters: ["bad\nparameter"]),
        ];
        yield 'empty middle parameter' => [
            'message' => new Message(command: 'TESTCMD', parameters: ['', 'something']),
        ];
        yield 'middle parameter beginning with a colon' => [
            'message' => new Message(command: 'TESTCMD', parameters: [':value', 'something']),
        ];
        yield 'middle parameter containing spaces' => [
            'message' => new Message(command: 'TESTCMD', parameters: ['hello world', 'something']),
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
