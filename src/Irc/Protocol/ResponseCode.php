<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

enum ResponseCode: string
{
    case Welcome = '001';
    case NoOrigin = '409';
    case UnknownCommand = '421';
    case NoNicknameGiven = '431';
    case ErroneousNickname = '432';
    case NicknameInUse = '433';

    public function defaultText(): ?string
    {
        return match ($this) {
            self::NoOrigin => 'No origin specified',
            self::UnknownCommand => 'Unknown command',
            self::NoNicknameGiven => 'No nickname given',
            self::ErroneousNickname => 'Erroneous nickname',
            self::NicknameInUse => 'Nickname is already in use',
            self::Welcome => null,
        };
    }
}
