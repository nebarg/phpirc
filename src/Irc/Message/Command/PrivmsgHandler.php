<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class PrivmsgHandler implements CommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private NumericResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'PRIVMSG';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $targets = $message->parameters[0] ?? '';

        if ($targets === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoRecipient,
                    target: $context->client->nickname,
                ),
            );

            return;
        }

        $text = $message->parameters[1] ?? '';

        if ($text === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoTextToSend,
                    target: $context->client->nickname,
                ),
            );

            return;
        }

        foreach (explode(',', $targets) as $target) {
            $channel = $this->channels->find($target);

            if ($channel !== null) {
                $this->broadcaster->broadcastExcept(
                    $channel,
                    new Message(
                        command: $this->command(),
                        parameters: [$channel->name, $text],
                        source: $context->client->nickname,
                    ),
                    $context->client,
                );

                continue;
            }

            $user = $this->clients->findByNickname($target);

            if ($user !== null) {
                $connection = $this->clients->connectionFor($user);

                if ($connection !== null) {
                    $connection->send(
                        new Message(
                            command: $this->command(),
                            parameters: [$user->nickname ?? $target, $text],
                            source: $context->client->nickname,
                        ),
                    );

                    continue;
                }
            }

            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoSuchNick,
                    target: $context->client->nickname,
                    parameters: [$target === '' ? '*' : $target],
                ),
            );
        }
    }
}
