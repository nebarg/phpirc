<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class PartHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function command(): string
    {
        return 'PART';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendMissingParametersResponse($context);
            return;
        }

        $reason = $message->optionalParameter(1);

        foreach (explode(',', $message->parameter(0)) as $channelName) {
            $this->leaveChannel($context, $channelName, $reason);
        }
    }

    private function sendMissingParametersResponse(CommandContext $context): void
    {
        $context->connection->send(
            $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
        );
    }

    private function leaveChannel(
        CommandContext $context,
        string $channelName,
        ?string $reason,
    ): void {
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $this->sendUnknownChannelResponse($context, $channelName);
            return;
        }

        if (! $channel->hasMember($context->client)) {
            $this->sendNotOnChannelResponse($context, $channel);
            return;
        }

        $this->broadcastPart($context, $channel, $reason);
        $this->channels->leave($channel, $context->client);
    }

    private function sendUnknownChannelResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->errors->noSuchChannel($context->responseTarget(), $channelName),
        );
    }

    private function sendNotOnChannelResponse(CommandContext $context, Channel $channel): void
    {
        $context->connection->send(
            $this->errors->notOnChannel($context->responseTarget(), $channel->name),
        );
    }

    private function broadcastPart(
        CommandContext $context,
        Channel $channel,
        ?string $reason,
    ): void {
        $parameters = $reason
            ? [$channel->name, $reason]
            : [$channel->name];

        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: $this->command(),
                parameters: $parameters,
                source: $context->actorNickname(),
            ),
        );
    }
}
