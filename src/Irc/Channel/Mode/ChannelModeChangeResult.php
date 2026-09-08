<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

final readonly class ChannelModeChangeResult
{
    /**
     * @param list<ChannelModeChange|MembershipModeChange> $appliedChanges
     * @param list<ModeChangeFailure> $failures
     * @param list<string> $unknownModes
     */
    public function __construct(
        public array $appliedChanges,
        public array $failures,
        public array $unknownModes,
    ) {}
}
