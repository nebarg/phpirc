<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Response\ChannelNamesResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class NamesHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelNamesResponseFactory $namesResponses,
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
                $this->namesResponses->createEndOfNamesResponse(
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
                    $this->namesResponses->createEndOfNamesResponse(
                        target: $target,
                        channelName: $channelName === '' ? '*' : $channelName,
                    ),
                );

                continue;
            }

            $context->connection->sendMany(
                $this->namesResponses->createNamesResponses($target, $channel),
            );
        }
    }
}
