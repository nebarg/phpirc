<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

use PhpIrc\Irc\Mode\ModeAction;

final readonly class MembershipModeChangeParser
{
    /** @param list<string> $arguments */
    public function parse(string $modeString, array $arguments): MembershipModeParseResult
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

            $mode = MembershipMode::tryFrom($character);

            if ($mode === null || $action === null) {
                $unknownModes[] = $character;
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
                mode: $mode,
                nickname: $nickname,
            );
        }

        return new MembershipModeParseResult($changes, $unknownModes);
    }
}
