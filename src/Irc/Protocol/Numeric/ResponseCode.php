<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Numeric;

enum ResponseCode: string
{
    case Welcome = '001';
    case YourHost = '002';
    case Created = '003';
    case MyInfo = '004';
    case ISupport = '005';
    case ListStart = '321';
    case ListEntry = '322';
    case ListEnd = '323';
    case NoTopic = '331';
    case Topic = '332';
    case TopicWhoTime = '333';
    case NamesReply = '353';
    case EndOfNames = '366';
    case NoSuchNick = '401';
    case NoSuchChannel = '403';
    case NoOrigin = '409';
    case InvalidCapCommand = '410';
    case NoRecipient = '411';
    case NoTextToSend = '412';
    case UnknownCommand = '421';
    case NoMotd = '422';
    case NoNicknameGiven = '431';
    case ErroneousNickname = '432';
    case NicknameInUse = '433';
    case NotOnChannel = '442';
    case NotRegistered = '451';
    case NeedMoreParameters = '461';
    case AlreadyRegistered = '462';

    public function defaultText(): ?string
    {
        // @mago-format-ignore-next
        return match ($this) {
            self::Welcome, self::YourHost, self::Created, self::MyInfo => null,
            self::NamesReply, self::ListEntry, self::Topic, self::TopicWhoTime => null,
            self::ISupport => 'are supported by this server',
            self::ListStart => 'Users  Name',
            self::ListEnd => 'End of /LIST',
            self::NoTopic => 'No topic is set',
            self::EndOfNames => 'End of /NAMES list',
            self::NoSuchNick => 'No such nick/channel',
            self::NoSuchChannel => 'No such channel',
            self::NoOrigin => 'No origin specified',
            self::InvalidCapCommand => 'Invalid CAP command',
            self::NoRecipient => 'No recipient given (PRIVMSG)',
            self::NoTextToSend => 'No text to send',
            self::UnknownCommand => 'Unknown command',
            self::NoMotd => 'MOTD File is missing',
            self::NoNicknameGiven => 'No nickname given',
            self::ErroneousNickname => 'Erroneous nickname',
            self::NicknameInUse => 'Nickname is already in use',
            self::NotOnChannel => 'You\'re not on that channel',
            self::NotRegistered => 'You have not registered',
            self::NeedMoreParameters => 'Not enough parameters',
            self::AlreadyRegistered => 'You may not reregister',
        };
    }
}
