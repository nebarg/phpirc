<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Network;

use PhpIrc\Irc\Network\NicknameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NicknameValidatorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validNicknames(): iterable
    {
        yield 'letters' => ['John'];
        yield 'numbers after first character' => ['John42'];
        yield 'hyphen after first character' => ['John-Test'];
        yield 'square brackets' => ['[John]'];
        yield 'curly brackets' => ['{John}'];
        yield 'backslash' => ['John\\Test'];
        yield 'pipe' => ['John|Test'];
        yield 'other traditional special characters' => ['G_`^'];
        yield 'maximum length' => [str_repeat('a', NicknameValidator::MAX_LENGTH)];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNicknames(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with number' => ['1John'];
        yield 'starts with hyphen' => ['-John'];
        yield 'channel prefix' => ['#John'];
        yield 'colon' => [':John'];
        yield 'dollar' => ['$John'];
        yield 'comma' => ['John,Test'];
        yield 'asterisk' => ['John*'];
        yield 'question mark' => ['John?'];
        yield 'exclamation mark' => ['John!'];
        yield 'at sign' => ['John@'];
        yield 'space' => ['John Test'];
        yield 'dot' => ['John.Test'];
        yield 'non-ascii' => ['Gránt'];
        yield 'over maximum length' => [str_repeat('a', NicknameValidator::MAX_LENGTH + 1)];
    }

    #[Test]
    #[DataProvider('validNicknames')]
    public function it_accepts_valid_nicknames(string $nickname): void
    {
        $this->assertTrue(new NicknameValidator()->isValid($nickname));
    }

    #[Test]
    #[DataProvider('invalidNicknames')]
    public function it_rejects_invalid_nicknames(string $nickname): void
    {
        $this->assertFalse(new NicknameValidator()->isValid($nickname));
    }
}
