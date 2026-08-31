<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol\Numeric;

use PhpIrc\Irc\Protocol\Numeric\ResponseCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResponseCodeTest extends TestCase
{
    /** @return iterable<string, array{ResponseCode, ?string}> */
    public static function defaultTexts(): iterable
    {
        yield 'welcome' => [ResponseCode::Welcome, null];
        yield 'your host' => [ResponseCode::YourHost, null];
        yield 'created' => [ResponseCode::Created, null];
        yield 'my info' => [ResponseCode::MyInfo, null];
        yield 'ISUPPORT' => [ResponseCode::ISupport, 'are supported by this server'];
        yield 'names reply' => [ResponseCode::NamesReply, null];
        yield 'end of names' => [ResponseCode::EndOfNames, 'End of /NAMES list'];
        yield 'no such channel' => [ResponseCode::NoSuchChannel, 'No such channel'];
        yield 'invalid CAP command' => [ResponseCode::InvalidCapCommand, 'Invalid CAP command'];
        yield 'no origin' => [ResponseCode::NoOrigin, 'No origin specified'];
        yield 'unknown command' => [ResponseCode::UnknownCommand, 'Unknown command'];
        yield 'no MOTD' => [ResponseCode::NoMotd, 'MOTD File is missing'];
        yield 'no nickname given' => [ResponseCode::NoNicknameGiven, 'No nickname given'];
        yield 'erroneous nickname' => [ResponseCode::ErroneousNickname, 'Erroneous nickname'];
        yield 'nickname in use' => [ResponseCode::NicknameInUse, 'Nickname is already in use'];
        yield 'not registered' => [ResponseCode::NotRegistered, 'You have not registered'];
        yield 'need more parameters' => [ResponseCode::NeedMoreParameters, 'Not enough parameters'];
        yield 'already registered' => [ResponseCode::AlreadyRegistered, 'You may not reregister'];
    }

    #[Test]
    #[DataProvider('defaultTexts')]
    public function it_provides_its_default_text(ResponseCode $code, ?string $text): void
    {
        $this->assertSame($text, $code->defaultText());
    }
}
