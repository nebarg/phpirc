<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\ChannelModeChanger;
use PhpIrc\Irc\Channel\Mode\ChannelModeChangeResult;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Channel\Response\ChannelModeResponseFactory;
use PhpIrc\Irc\Channel\Response\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Protocol\Message;

final readonly class ChannelModeHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private ChannelModeChanger $modeChanger,
        private ChannelModeResponseFactory $modeResponses,
        private ChannelAccessPolicy $channelAccess,
        private ChannelPermissionResponseFactory $permissionResponses,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $channelName = $message->parameter(0);
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $this->sendUnknownChannelResponse($context, $channelName);
            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendCurrentModes($context, $channel);
            return;
        }

        $permission = $this->channelAccess->canChangeMode($channel, $context->client);

        if ($permission->isDenied()) {
            $this->sendPermissionDeniedResponse($context, $channel, $permission);
            return;
        }

        $result = $this->applyChanges($channel, $message);

        $this->sendUnknownModeResponses($context, $result);
        $this->sendChangeFailureResponses($context, $channel, $result);
        $this->broadcastAppliedChanges($context, $channel, $result);
    }

    private function sendUnknownChannelResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->modeResponses->createUnknownChannelResponse(
                $context->responseTarget(),
                $channelName,
            ),
        );
    }

    private function sendCurrentModes(CommandContext $context, Channel $channel): void
    {
        $context->connection->sendMany(
            $this->modeResponses->createCurrentModeResponses($context->responseTarget(), $channel),
        );
    }

    private function sendPermissionDeniedResponse(
        CommandContext $context,
        Channel $channel,
        ChannelPermission $permission,
    ): void {
        $context->connection->send(
            $this->permissionResponses->createModeChangeDeniedResponse(
                $permission,
                $context->responseTarget(),
                $channel,
            ),
        );
    }

    private function applyChanges(Channel $channel, Message $message): ChannelModeChangeResult
    {
        return $this->modeChanger->apply(
            channel: $channel,
            modeString: $message->parameter(1),
            arguments: array_slice($message->parameters, 2),
        );
    }

    private function sendUnknownModeResponses(CommandContext $context, ChannelModeChangeResult $result): void
    {
        foreach ($result->unknownModes as $unknownMode) {
            $context->connection->send(
                $this->modeResponses->createUnknownModeResponse(
                    $context->responseTarget(),
                    $unknownMode,
                ),
            );
        }
    }

    private function sendChangeFailureResponses(
        CommandContext $context,
        Channel $channel,
        ChannelModeChangeResult $result,
    ): void {
        foreach ($result->failures as $failure) {
            $context->connection->send(
                $this->modeResponses->createChangeFailureResponse(
                    $context->responseTarget(),
                    $channel,
                    $failure,
                ),
            );
        }
    }

    private function broadcastAppliedChanges(
        CommandContext $context,
        Channel $channel,
        ChannelModeChangeResult $result,
    ): void {
        if ($result->appliedChanges === []) {
            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            $this->modeResponses->createChangedMessage(
                $context->actorNickname(),
                $channel,
                $result->appliedChanges,
            ),
        );
    }
}
