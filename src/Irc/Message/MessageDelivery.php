<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\Message;

final readonly class MessageDelivery
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
    ) {}

    /** @return list<string> */
    public function deliver(
        Client $sender,
        string $command,
        string $targets,
        string $text,
    ): array {
        $unresolvedTargets = [];

        foreach (explode(',', $targets) as $target) {
            if ($this->deliverToChannel($sender, $command, $target, $text)) {
                continue;
            }

            if ($this->deliverToClient($sender, $command, $target, $text)) {
                continue;
            }

            $unresolvedTargets[] = $target;
        }

        return $unresolvedTargets;
    }

    private function deliverToChannel(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): bool {
        $channel = $this->channels->find($target);

        if ($channel === null) {
            return false;
        }

        $this->broadcaster->broadcastExcept(
            $channel,
            new Message(
                command: $command,
                parameters: [$channel->name, $text],
                source: $sender->nickname,
            ),
            $sender,
        );

        return true;
    }

    private function deliverToClient(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): bool {
        $recipient = $this->clients->findByNickname($target);

        if ($recipient === null) {
            return false;
        }

        $connection = $this->clients->connectionFor($recipient);

        if ($connection === null) {
            return false;
        }

        $connection->send(new Message(
            command: $command,
            parameters: [$recipient->nickname ?? $target, $text],
            source: $sender->nickname,
        ));

        return true;
    }
}
