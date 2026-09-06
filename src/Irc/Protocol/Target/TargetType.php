<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Target;

enum TargetType
{
    case Channel;
    case Nickname;
}
