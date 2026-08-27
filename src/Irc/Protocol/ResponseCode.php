<?php

namespace PhpIrc\Irc\Protocol;

enum ResponseCode: string
{
    case NoOrigin = '409';
    case UnknownCommand = '421';
}
