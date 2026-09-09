<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Channel\Response\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class KickHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ClientRegistry $clients,
        private ChannelBroadcaster $broadcaster,
        private ChannelAccessPolicy $channelAccess,
        private ChannelPermissionResponseFactory $permissionResponses,
        private NumericErrorResponseFactory $errors,
        private MessageTextLimiter $messageText,
    ) {}

    public function command(): string
    {
        return 'KICK';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0) || $message->isParameterMissingOrEmpty(1)) {
            $this->sendMissingParametersResponse($context);
            return;
        }

        $channelName = $message->parameter(0);
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $this->sendUnknownChannelResponse($context, $channelName);
            return;
        }

        $permission = $this->channelAccess->canKick($channel, $context->client);

        if ($permission->isDenied()) {
            $this->sendPermissionDeniedResponse($context, $channel, $permission);
            return;
        }

        $requestedNickname = $message->parameter(1);
        $client = $this->clients->findByNickname($requestedNickname);
        $targetNickname = $client->nickname ?? $requestedNickname;

        if ($client === null || ! $channel->hasMember($client)) {
            $this->sendTargetNotInChannelResponse($context, $channel, $targetNickname);
            return;
        }

        $this->performKick($context, $message, $channel, $client, $targetNickname);
    }

    private function sendMissingParametersResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
        );
    }

    private function sendUnknownChannelResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->errors->noSuchChannel($context->responseTarget(), $channelName),
        );
    }

    private function sendPermissionDeniedResponse(
        CommandContext $context,
        Channel $channel,
        ChannelPermission $permission,
    ): void {
        $context->connection->send(
            $this->permissionResponses->createKickDeniedResponse(
                $permission,
                $context->responseTarget(),
                $channel,
            ),
        );
    }

    private function sendTargetNotInChannelResponse(
        CommandContext $context,
        Channel $channel,
        string $targetNickname,
    ): void {
        $context->connection->send(
            $this->errors->userNotInChannel(
                $context->responseTarget(),
                $targetNickname,
                $channel->name,
            ),
        );
    }

    private function performKick(
        CommandContext $context,
        Message $message,
        Channel $channel,
        Client $client,
        string $targetNickname,
    ): void {
        $actorNickname = $context->actorNickname();

        $this->broadcaster->broadcast(
            $channel,
            $this->messageText->limit(new Message(
                command: $this->command(),
                parameters: [
                    $channel->name,
                    $targetNickname,
                    $message->optionalParameter(2) ?? $actorNickname,
                ],
                source: $actorNickname,
            )),
        );

        $this->channels->leave($channel, $client);
    }
}
