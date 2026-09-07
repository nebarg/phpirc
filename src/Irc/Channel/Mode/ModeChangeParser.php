<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

use PhpIrc\Irc\Mode\ModeAction;

final readonly class ModeChangeParser
{
    /** @param list<string> $arguments */
    public function parse(string $modeString, array $arguments): ModeParseResult
    {
        $changes = [];
        $unknownModes = [];
        $action = null;
        $argumentPosition = 0;

        for ($position = 0; $position < strlen($modeString); $position++) {
            $character = $modeString[$position];
            $nextAction = ModeAction::tryFrom($character);

            if ($nextAction !== null) {
                $action = $nextAction;
                continue;
            }

            $channelMode = ChannelMode::tryFrom($character);
            $membershipMode = MembershipMode::tryFrom($character);

            if ($channelMode === null && $membershipMode === null || $action === null) {
                $unknownModes[] = $character;
                continue;
            }

            if ($channelMode !== null) {
                $changes[] = new ChannelModeChange(
                    action: $action,
                    mode: $channelMode,
                );

                continue;
            }

            $nickname = $arguments[$argumentPosition] ?? null;

            if ($nickname === null) {
                continue;
            }

            $argumentPosition++;

            if ($nickname === '') {
                continue;
            }

            $changes[] = new MembershipModeChange(
                action: $action,
                mode: $membershipMode,
                nickname: $nickname,
            );
        }

        return new ModeParseResult($changes, $unknownModes);
    }
}
