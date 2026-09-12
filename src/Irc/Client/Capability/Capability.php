<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Capability;

enum Capability: string
{
    case ServerTime = 'server-time';
}
