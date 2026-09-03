<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Command;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelListResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class ListHandler implements CommandHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ChannelListResponseFactory $responses,
    ) {}

    public function command(): string
    {
        return 'LIST';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $channels = $message->isParameterMissingOrEmpty(0)
            ? $this->channels->all()
            : $this->findChannels($message->parameter(0));

        array_map(
            $context->connection->send(...),
            $this->responses->createResponses($context->responseTarget(), $channels),
        );
    }

    /** @return list<Channel> */
    private function findChannels(string $channels): array
    {
        $result = [];

        foreach (explode(',', $channels) as $channel) {
            $found = $this->channels->find($channel);

            if ($found !== null) {
                $result[] = $found;
            }
        }

        return $result;
    }
}
