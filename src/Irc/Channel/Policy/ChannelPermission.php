<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Policy;

enum ChannelPermission
{
    case Allowed;
    case NotMember;
    case InsufficientPrivileges;

    public function isDenied(): bool
    {
        return $this !== self::Allowed;
    }
}
