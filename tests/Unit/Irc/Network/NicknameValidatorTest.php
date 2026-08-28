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
        yield 'letters' => ['Grant'];
        yield 'numbers after first character' => ['Grant42'];
        yield 'hyphen after first character' => ['Grant-Test'];
        yield 'square brackets' => ['[Grant]'];
        yield 'curly brackets' => ['{Grant}'];
        yield 'backslash' => ['Grant\\Test'];
        yield 'pipe' => ['Grant|Test'];
        yield 'other traditional special characters' => ['G_`^'];
        yield 'maximum length' => [str_repeat('a', NicknameValidator::MAX_LENGTH)];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNicknames(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with number' => ['1Grant'];
        yield 'starts with hyphen' => ['-Grant'];
        yield 'channel prefix' => ['#Grant'];
        yield 'colon' => [':Grant'];
        yield 'dollar' => ['$Grant'];
        yield 'comma' => ['Grant,Test'];
        yield 'asterisk' => ['Grant*'];
        yield 'question mark' => ['Grant?'];
        yield 'exclamation mark' => ['Grant!'];
        yield 'at sign' => ['Grant@'];
        yield 'space' => ['Grant Test'];
        yield 'dot' => ['Grant.Test'];
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
