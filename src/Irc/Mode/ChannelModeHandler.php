<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\ChannelModeChanger;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
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
        $channel = $this->channels->find($message->parameter(0));

        if ($channel === null) {
            $context->connection->send(
                $this->modeResponses->createUnknownChannelResponse(
                    $context->responseTarget(),
                    $message->parameter(0),
                ),
            );

            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendCurrentModes($context, $channel);
            return;
        }

        $permission = $this->channelAccess->canChangeMode($channel, $context->client);

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

        $result = $this->modeChanger->apply(
            channel: $channel,
            modeString: $message->parameter(1),
            arguments: array_slice($message->parameters, 2),
        );

        foreach ($result->unknownModes as $unknownMode) {
            $context->connection->send(
                $this->modeResponses->createUnknownModeResponse(
                    $context->responseTarget(),
                    $unknownMode,
                ),
            );
        }

        foreach ($result->failures as $failure) {
            $context->connection->send(
                $this->modeResponses->createChangeFailureResponse(
                    $context->responseTarget(),
                    $channel,
                    $failure,
                ),
            );
        }

        if ($result->appliedChanges === []) {
            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            $this->modeResponses->createChangedMessage(
                $context->responseTarget(),
                $channel,
                $result->appliedChanges,
            ),
        );
    }

    private function sendCurrentModes(CommandContext $context, Channel $channel): void
    {
        $context->connection->sendMany(
            $this->modeResponses->createCurrentModeResponses($context->responseTarget(), $channel),
        );
    }
}
