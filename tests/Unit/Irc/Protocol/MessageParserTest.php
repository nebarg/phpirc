<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\InvalidMessage;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Protocol\MessageTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class MessageParserTest extends IntegrationTestCase
{
    #[Test]
    public function it_parses_a_command_and_middle_parameter(): void
    {
        $message = new MessageParser()->parse('NICK Grant');

        $this->assertSame([], $message->tags);
        $this->assertNull($message->source);
        $this->assertSame('NICK', $message->command);
        $this->assertSame(['Grant'], $message->parameters);
    }

    #[Test]
    public function it_normalizes_named_commands_to_uppercase(): void
    {
        $message = new MessageParser()->parse('privmsg #php :Hello everyone');

        $this->assertSame('PRIVMSG', $message->command);
        $this->assertSame(['#php', 'Hello everyone'], $message->parameters);
    }

    #[Test]
    public function it_parses_a_source_and_numeric_command(): void
    {
        $message = new MessageParser()->parse(':irc.example 001 Grant :Welcome');

        $this->assertSame('irc.example', $message->source);
        $this->assertSame('001', $message->command);
        $this->assertSame(['Grant', 'Welcome'], $message->parameters);
    }

    #[Test]
    public function it_preserves_an_empty_trailing_parameter(): void
    {
        $message = new MessageParser()->parse('TOPIC #php :');

        $this->assertSame(['#php', ''], $message->parameters);
    }

    #[Test]
    public function it_tolerates_repeated_separating_spaces_and_preserves_trailing_spaces(): void
    {
        $message = new MessageParser()->parse('PRIVMSG   #php   :hello  there ');

        $this->assertSame(['#php', 'hello  there '], $message->parameters);
    }

    #[Test]
    public function it_only_treats_a_colon_as_special_at_the_start_of_a_parameter(): void
    {
        $message = new MessageParser()->parse('COMMAND abc:def ::value');

        $this->assertSame(['abc:def', ':value'], $message->parameters);
    }

    #[Test]
    public function it_parses_and_unescapes_tags(): void
    {
        $message = new MessageParser()->parse(
            '@aaa=bbb;ccc;empty=;space=hello\sworld;semi=a\:b;slash=a\\\\b;unknown=a\qb;lone=end\\ COMMAND',
        );

        $this->assertEquals(
            [
                new MessageTag('aaa', 'bbb'),
                new MessageTag('ccc', null),
                new MessageTag('empty', null),
                new MessageTag('space', 'hello world'),
                new MessageTag('semi', 'a;b'),
                new MessageTag('slash', 'a\\b'),
                new MessageTag('unknown', 'aqb'),
                new MessageTag('lone', 'end'),
            ],
            $message->tags,
        );
    }

    #[Test]
    public function it_treats_tag_names_as_case_sensitive_and_keeps_the_final_duplicate(): void
    {
        $message = new MessageParser()->parse('@A=first;a=lower;A=final COMMAND');

        $this->assertEquals(
            [
                new MessageTag('A', 'final'),
                new MessageTag('a', 'lower'),
            ],
            $message->tags,
        );
    }

    #[Test]
    public function it_parses_tags_source_command_and_parameters_together(): void
    {
        $message = new MessageParser()->parse(
            '@time=2026-08-24T12:00:00.000Z :nick!user@host PRIVMSG #php :Hello everyone',
        );

        $this->assertEquals(
            [new MessageTag('time', '2026-08-24T12:00:00.000Z')],
            $message->tags,
        );
        $this->assertSame('nick!user@host', $message->source);
        $this->assertSame('PRIVMSG', $message->command);
        $this->assertSame(['#php', 'Hello everyone'], $message->parameters);
    }

    #[Test]
    public function it_does_not_treat_tabs_as_component_separators(): void
    {
        $message = new MessageParser()->parse(":nick\tname COMMAND parameter");

        $this->assertSame("nick\tname", $message->source);
        $this->assertSame('COMMAND', $message->command);
        $this->assertSame(['parameter'], $message->parameters);
    }

    #[Test]
    public function it_treats_tag_names_as_opaque(): void
    {
        $message = new MessageParser()->parse('@not?normally=valid COMMAND');

        $this->assertEquals(
            [new MessageTag('not?normally', 'valid')],
            $message->tags,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'empty message' => [''];
        yield 'leading space' => [' NICK Grant'];
        yield 'tags without command' => ['@a=b'];
        yield 'source without command' => [':nick'];
        yield 'empty source' => [': PRIVMSG #php :Hello'];
        yield 'short numeric command' => ['12'];
        yield 'long numeric command' => ['0001'];
        yield 'alphanumeric command' => ['PRIVMSG1 #php :Hello'];
        yield 'embedded null' => ["NICK bad\0nick"];
        yield 'carriage return and line feed' => ["NICK Grant\r\n"];
    }

    #[Test]
    #[DataProvider('invalidMessages')]
    public function it_rejects_malformed_messages(string $line): void
    {
        $this->expectException(InvalidMessage::class);

        new MessageParser()->parse($line);
    }
}
