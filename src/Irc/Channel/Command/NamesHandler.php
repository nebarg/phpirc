<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelNamesResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class NamesHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelNamesResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'NAMES';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $target = $context->responseTarget();

        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->responses->createEndResponse(
                    target: $target,
                    channelName: '*',
                ),
            );

            return;
        }

        $channels = $message->parameter(0);

        foreach (explode(',', $channels) as $channelName) {
            $channel = $this->channels->find($channelName);

            if ($channel === null) {
                $context->connection->send(
                    $this->responses->createEndResponse(
                        target: $target,
                        channelName: $channelName === '' ? '*' : $channelName,
                    ),
                );

                continue;
            }

            array_map(
                $context->connection->send(...),
                $this->responses->createResponses($target, $channel)
            );
        }
    }
}
