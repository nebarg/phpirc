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
    case NamesReply = '353';
    case EndOfNames = '366';
    case NoSuchChannel = '403';
    case NoOrigin = '409';
    case InvalidCapCommand = '410';
    case UnknownCommand = '421';
    case NoMotd = '422';
    case NoNicknameGiven = '431';
    case ErroneousNickname = '432';
    case NicknameInUse = '433';
    case NotRegistered = '451';
    case NeedMoreParameters = '461';
    case AlreadyRegistered = '462';

    public function defaultText(): ?string
    {
        return match ($this) {
            self::Welcome, self::YourHost, self::Created, self::MyInfo, self::NamesReply => null,
            self::ISupport => 'are supported by this server',
            self::EndOfNames => 'End of /NAMES list',
            self::NoSuchChannel => 'No such channel',
            self::NoOrigin => 'No origin specified',
            self::InvalidCapCommand => 'Invalid CAP command',
            self::UnknownCommand => 'Unknown command',
            self::NoMotd => 'MOTD File is missing',
            self::NoNicknameGiven => 'No nickname given',
            self::ErroneousNickname => 'Erroneous nickname',
            self::NicknameInUse => 'Nickname is already in use',
            self::NotRegistered => 'You have not registered',
            self::NeedMoreParameters => 'Not enough parameters',
            self::AlreadyRegistered => 'You may not reregister',
        };
    }
}
