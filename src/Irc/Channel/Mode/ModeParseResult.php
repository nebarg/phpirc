<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

final readonly class ModeParseResult
{
    /**
     * @param list<ChannelModeChange|MembershipModeChange> $changes
     * @param list<string> $unknownModes
     */
    public function __construct(
        public array $changes,
        public array $unknownModes,
    ) {}
}
