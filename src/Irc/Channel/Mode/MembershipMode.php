<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

enum MembershipMode: string
{
    case Operator = 'o';
    case Voice = 'v';

    public function prefix(): string
    {
        return match ($this) {
            self::Operator => '@',
            self::Voice => '+',
        };
    }

    public function prefixRank(): int
    {
        return match ($this) {
            self::Operator => 2,
            self::Voice => 1,
        };
    }
}
