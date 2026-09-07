<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelModeResponseFactory;
use PhpIrc\Irc\Channel\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class ChannelModeHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ClientRegistry $clients,
        private ChannelBroadcaster $broadcaster,
        private ModeChangeParser $parser,
        private NumericErrorResponseFactory $errors,
        private ChannelModeResponseFactory $modeResponses,
        private ChannelAccessPolicy $channelAccess,
        private ChannelPermissionResponseFactory $permissionResponses,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $channel = $this->channels->find($message->parameter(0));

        if ($channel === null) {
            $context->connection->send(
                $this->errors->noSuchChannel($context->responseTarget(), $message->parameter(0)),
            );

            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendCurrentModes($context, $channel);
            return;
        }

        $permission = $this->channelAccess->checkModeChange($channel, $context->client);

        if ($permission->isDenied()) {
            $context->connection->send(
                $this->permissionResponses->createModeChangeDeniedResponse(
                    $permission,
                    $context->responseTarget(),
                    $channel,
                ),
            );

            return;
        }

        $result = $this->parser->parse(
            modeString: $message->parameter(1),
            arguments: array_slice($message->parameters, 2),
        );

        foreach ($result->unknownModes as $unknownMode) {
            $context->connection->send(
                $this->errors->unknownMode($context->responseTarget(), $unknownMode),
            );
        }

        $appliedChanges = [];

        foreach ($result->changes as $change) {
            $appliedChange = $this->applyChange($context, $channel, $change);

            if ($appliedChange === null) {
                continue;
            }

            $appliedChanges[] = $appliedChange;
        }

        if ($appliedChanges === []) {
            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            $this->modeResponses->createChangedMessage(
                $context->responseTarget(),
                $channel,
                $appliedChanges,
            ),
        );
    }

    private function sendCurrentModes(CommandContext $context, Channel $channel): void
    {
        $context->connection->sendMany(
            $this->modeResponses->createCurrentModeResponses($context->responseTarget(), $channel),
        );
    }

    private function applyChange(
        CommandContext $context,
        Channel $channel,
        ChannelModeChange|MembershipModeChange $change,
    ): ChannelModeChange|MembershipModeChange|null {
        if ($change instanceof ChannelModeChange) {
            $changed = match ($change->action) {
                ModeAction::Add => $channel->enableMode($change->mode),
                ModeAction::Remove => $channel->disableMode($change->mode),
            };

            return $changed ? $change : null;
        }

        $targetMembership = $this->findTargetMembership($context, $channel, $change->nickname);

        if ($targetMembership === null) {
            return null;
        }

        $changed = match ($change->action) {
            ModeAction::Add => $targetMembership->grant($change->mode),
            ModeAction::Remove => $targetMembership->revoke($change->mode),
        };

        if (! $changed) {
            return null;
        }

        return new MembershipModeChange(
            action: $change->action,
            mode: $change->mode,
            nickname: $targetMembership->client->nickname ?? $change->nickname,
        );
    }

    private function findTargetMembership(
        CommandContext $context,
        Channel $channel,
        string $nickname,
    ): ?Membership {
        $client = $this->clients->findByNickname($nickname);

        if ($client === null || ! $client->registration->isComplete()) {
            $context->connection->send(
                $this->errors->noSuchNickname($context->responseTarget(), $nickname),
            );

            return null;
        }

        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            $context->connection->send(
                $this->errors->userNotInChannel(
                    $context->responseTarget(),
                    $client->nickname ?? $nickname,
                    $channel->name,
                ),
            );
        }

        return $membership;
    }
}
