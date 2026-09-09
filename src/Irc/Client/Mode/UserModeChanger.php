<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Mode;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Mode\ModeAction;

final readonly class UserModeChanger
{
    public function apply(Client $client, string $modeString): UserModeChangeResult
    {
        $appliedChanges = [];
        $hasUnknownModes = false;
        $action = null;

        for ($position = 0; $position < strlen($modeString); $position++) {
            $character = $modeString[$position];
            $nextAction = ModeAction::tryFrom($character);

            if ($nextAction !== null) {
                $action = $nextAction;
                continue;
            }

            $mode = UserMode::tryFrom($character);

            if ($mode === null || $action === null) {
                $hasUnknownModes = true;
                continue;
            }

            $changed = match ($action) {
                ModeAction::Add => $client->enableMode($mode),
                ModeAction::Remove => $client->disableMode($mode),
            };

            if ($changed) {
                $appliedChanges[] = new UserModeChange($action, $mode);
            }
        }

        return new UserModeChangeResult($appliedChanges, $hasUnknownModes);
    }
}
