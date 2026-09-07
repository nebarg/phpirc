<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

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
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $channels = $message->parameter(0);
        $leavingMessage = $message->optionalParameter(1);

        foreach (explode(',', $channels) as $channelName) {
            $channel = $this->channels->find($channelName);

            if ($channel === null) {
                $context->connection->send(
                    $this->errors->noSuchChannel($context->responseTarget(), $channelName),
                );

                continue;
            }

            if (! $channel->hasMember($context->client)) {
                $context->connection->send(
                    $this->errors->notOnChannel($context->responseTarget(), $channel->name),
                );

                continue;
            }

            $params = $leavingMessage
                ? [$channel->name, $leavingMessage]
                : [$channel->name];

            $this->broadcaster->broadcast(
                $channel,
                new Message(
                    command: $this->command(),
                    parameters: $params,
                    source: $context->client->nickname,
                ),
            );

            $this->channels->leave($channel, $context->client);
        }
    }
}
