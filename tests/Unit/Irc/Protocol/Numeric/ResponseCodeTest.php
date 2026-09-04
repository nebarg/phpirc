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
        yield 'end of WHO' => [ResponseCode::EndOfWho, 'End of WHO list'];
        yield 'list start' => [ResponseCode::ListStart, 'Users  Name'];
        yield 'list entry' => [ResponseCode::ListEntry, null];
        yield 'list end' => [ResponseCode::ListEnd, 'End of /LIST'];
        yield 'no topic' => [ResponseCode::NoTopic, 'No topic is set'];
        yield 'topic' => [ResponseCode::Topic, null];
        yield 'topic setter and time' => [ResponseCode::TopicWhoTime, null];
        yield 'WHO reply' => [ResponseCode::WhoReply, null];
        yield 'names reply' => [ResponseCode::NamesReply, null];
        yield 'end of names' => [ResponseCode::EndOfNames, 'End of /NAMES list'];
        yield 'no such nick' => [ResponseCode::NoSuchNick, 'No such nick/channel'];
        yield 'no such channel' => [ResponseCode::NoSuchChannel, 'No such channel'];
        yield 'invalid CAP command' => [ResponseCode::InvalidCapCommand, 'Invalid CAP command'];
        yield 'no origin' => [ResponseCode::NoOrigin, 'No origin specified'];
        yield 'no recipient' => [ResponseCode::NoRecipient, 'No recipient given (PRIVMSG)'];
        yield 'no text to send' => [ResponseCode::NoTextToSend, 'No text to send'];
        yield 'unknown command' => [ResponseCode::UnknownCommand, 'Unknown command'];
        yield 'no MOTD' => [ResponseCode::NoMotd, 'MOTD File is missing'];
        yield 'no nickname given' => [ResponseCode::NoNicknameGiven, 'No nickname given'];
        yield 'erroneous nickname' => [ResponseCode::ErroneousNickname, 'Erroneous nickname'];
        yield 'nickname in use' => [ResponseCode::NicknameInUse, 'Nickname is already in use'];
        yield 'not on channel' => [ResponseCode::NotOnChannel, "You're not on that channel"];
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
