<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use PhpIrc\Irc\Protocol\ResponseCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResponseCodeTest extends TestCase
{
    /** @return iterable<string, array{ResponseCode, ?string}> */
    public static function defaultTexts(): iterable
    {
        yield 'welcome' => [ResponseCode::Welcome, null];
        yield 'no origin' => [ResponseCode::NoOrigin, 'No origin specified'];
        yield 'unknown command' => [ResponseCode::UnknownCommand, 'Unknown command'];
        yield 'no nickname given' => [ResponseCode::NoNicknameGiven, 'No nickname given'];
        yield 'erroneous nickname' => [ResponseCode::ErroneousNickname, 'Erroneous nickname'];
        yield 'nickname in use' => [ResponseCode::NicknameInUse, 'Nickname is already in use'];
    }

    #[Test]
    #[DataProvider('defaultTexts')]
    public function it_provides_its_default_text(ResponseCode $code, ?string $text): void
    {
        $this->assertSame($text, $code->defaultText());
    }
}
