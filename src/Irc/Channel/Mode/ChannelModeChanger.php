<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Mode\ModeAction;

final readonly class ChannelModeChanger
{
    public function __construct(
        private ModeChangeParser $parser,
        private ClientRegistry $clients,
    ) {}

    /** @param list<string> $arguments */
    public function apply(Channel $channel, string $modeString, array $arguments): ChannelModeChangeResult
    {
        $parsed = $this->parser->parse($modeString, $arguments);
        $appliedChanges = [];
        $failures = [];

        foreach ($parsed->changes as $change) {
            if ($change instanceof ChannelModeChange) {
                if ($this->applyChannelModeChange($channel, $change)) {
                    $appliedChanges[] = $change;
                }

                continue;
            }

            $result = $this->applyMembershipModeChange($channel, $change);

            if ($result instanceof ModeChangeFailure) {
                $failures[] = $result;
            } elseif ($result !== null) {
                $appliedChanges[] = $result;
            }
        }

        return new ChannelModeChangeResult(
            appliedChanges: $appliedChanges,
            failures: $failures,
            unknownModes: $parsed->unknownModes,
        );
    }

    private function applyChannelModeChange(Channel $channel, ChannelModeChange $change): bool
    {
        return match ($change->action) {
            ModeAction::Add => $channel->enableMode($change->mode),
            ModeAction::Remove => $channel->disableMode($change->mode),
        };
    }

    private function applyMembershipModeChange(
        Channel $channel,
        MembershipModeChange $change,
    ): MembershipModeChange|ModeChangeFailure|null {
        $client = $this->clients->findByNickname($change->nickname);

        if ($client === null || ! $client->registration->isComplete()) {
            return new ModeChangeFailure(
                nickname: $change->nickname,
                reason: ModeChangeFailureReason::NoSuchNickname,
            );
        }

        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            return new ModeChangeFailure(
                nickname: $client->nickname ?? $change->nickname,
                reason: ModeChangeFailureReason::UserNotInChannel,
            );
        }

        $changed = match ($change->action) {
            ModeAction::Add => $membership->grant($change->mode),
            ModeAction::Remove => $membership->revoke($change->mode),
        };

        if (! $changed) {
            return null;
        }

        return new MembershipModeChange(
            action: $change->action,
            mode: $change->mode,
            nickname: $client->nickname ?? $change->nickname,
        );
    }
}
