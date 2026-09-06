<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

enum ModeAction: string
{
    case Add = '+';
    case Remove = '-';
}
