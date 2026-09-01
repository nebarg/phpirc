<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class PartHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private NumericResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'PART';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $channels = $message->parameters[0] ?? '';

        if ($channels === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NeedMoreParameters,
                    target: $context->client->nickname,
                    parameters: [$this->command()],
                ),
            );

            return;
        }

        $leavingMessage = $message->parameters[1] ?? null;

        foreach (explode(',', $channels) as $channelName) {
            $channel = $this->channels->find($channelName);

            if ($channel === null) {
                $context->connection->send(
                    $this->responses->create(
                        code: ResponseCode::NoSuchChannel,
                        target: $context->client->nickname,
                        parameters: [$channelName === '' ? '*' : $channelName],
                    ),
                );

                continue;
            }

            if (! $channel->has($context->client)) {
                $context->connection->send(
                    $this->responses->create(
                        code: ResponseCode::NotOnChannel,
                        target: $context->client->nickname,
                        parameters: [$channel->name],
                    ),
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
