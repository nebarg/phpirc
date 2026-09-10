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
        if ($message->isParameterMissingOrEmpty(0)) {
            $this->sendEndOfNamesResponse($context, '*');
            return;
        }

        foreach (explode(',', $message->parameter(0)) as $channelName) {
            $this->sendNamesForChannel($context, $channelName);
        }
    }

    private function sendNamesForChannel(CommandContext $context, string $channelName): void
    {
        $channel = $this->channels->find($channelName);

        if ($channel === null) {
            $this->sendEndOfNamesResponse($context, $channelName === '' ? '*' : $channelName);
            return;
        }

        $context->connection->sendMany(
            $this->namesResponses->createNamesResponses(
                $context->responseTarget(),
                $context->client,
                $channel,
            ),
        );
    }

    private function sendEndOfNamesResponse(CommandContext $context, string $channelName): void
    {
        $context->connection->send(
            $this->namesResponses->createEndOfNamesResponse(
                target: $context->responseTarget(),
                channelName: $channelName,
            ),
        );
    }
}
