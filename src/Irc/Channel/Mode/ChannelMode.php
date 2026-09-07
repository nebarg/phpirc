<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

enum ChannelMode: string
{
    case Moderated = 'm';
    case NoExternalMessages = 'n';
    case ProtectedTopic = 't';
}
