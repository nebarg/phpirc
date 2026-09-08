<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

enum ModeChangeFailureReason
{
    case NoSuchNickname;
    case UserNotInChannel;
}
