<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Response\ChannelPermissionResponseFactory;
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
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $channelName = $message->parameter(0);
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $context->connection->send(
                $this->errors->noSuchChannel($context->responseTarget(), $channelName),
            );

            return;
        }

        $permission = $this->channelAccess->canKick($channel, $context->client);

        if ($permission->isDenied()) {
            $context->connection->send(
                $this->permissionResponses->createKickDeniedResponse(
                    $permission,
                    $context->responseTarget(),
                    $channel,
                ),
            );

            return;
        }

        $nickname = $message->parameter(1);
        $client = $this->clients->findByNickname($nickname);
        $targetNickname = $client === null
            ? $nickname
            : $client->nickname ?? $nickname;

        if ($client === null || ! $channel->hasMember($client)) {
            $context->connection->send(
                $this->errors->userNotInChannel(
                    $context->responseTarget(),
                    $targetNickname,
                    $channel->name,
                ),
            );

            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            $this->messageText->limit(new Message(
                command: $this->command(),
                parameters: [
                    $channel->name,
                    $targetNickname,
                    $message->optionalParameter(2) ?? $context->responseTarget(),
                ],
                source: $context->client->nickname,
            )),
        );

        $this->channels->leave($channel, $client);
    }
}
