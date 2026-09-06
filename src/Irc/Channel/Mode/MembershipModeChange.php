<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

use PhpIrc\Irc\Mode\ModeAction;

final readonly class MembershipModeChange
{
    public function __construct(
        public ModeAction $action,
        public MembershipMode $mode,
        public string $nickname,
    ) {}
}
