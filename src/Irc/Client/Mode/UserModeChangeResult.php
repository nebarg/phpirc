<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Mode;

final readonly class UserModeChangeResult
{
    /** @param list<UserModeChange> $appliedChanges */
    public function __construct(
        public array $appliedChanges,
        public bool $hasUnknownModes,
    ) {}
}
