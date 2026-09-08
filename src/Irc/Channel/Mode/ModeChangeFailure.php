<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

final readonly class ModeChangeFailure
{
    public function __construct(
        public string $nickname,
        public ModeChangeFailureReason $reason,
    ) {}
}
