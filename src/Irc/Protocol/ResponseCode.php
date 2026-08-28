<?php

namespace PhpIrc\Irc\Protocol;

enum ResponseCode: string
{
    case Welcome = '001';
    case NoOrigin = '409';
    case UnknownCommand = '421';
    case NoNicknameGiven = '431';
    case ErroneousNickname = '432';
    case NicknameInUse = '433';
}
