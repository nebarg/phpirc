<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Mode;

use PhpIrc\Irc\Mode\ModeAction;

final readonly class UserModeChange
{
    public function __construct(
        public ModeAction $action,
        public UserMode $mode,
    ) {}
}
