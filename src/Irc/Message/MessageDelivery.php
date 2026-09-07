<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PhpIrc\Irc\Protocol\Target\TargetType;

final readonly class MessageDelivery
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private TargetClassifier $targets,
    ) {}

    /** @return list<MessageDeliveryFailure> */
    public function deliver(
        Client $sender,
        string $command,
        string $targets,
        string $text,
    ): array {
        $failures = [];

        foreach (explode(',', $targets) as $target) {
            $failure = match ($this->targets->classify($target)) {
                TargetType::Channel => $this->deliverToChannel($sender, $command, $target, $text),
                TargetType::Nickname => $this->deliverToClient($sender, $command, $target, $text),
            };

            if ($failure === null) {
                continue;
            }

            $failures[] = $failure;
        }

        return $failures;
    }

    private function deliverToChannel(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): ?MessageDeliveryFailure {
        $channel = $this->channels->find($target);

        if ($channel === null) {
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::TargetNotFound);
        }

        if (! $channel->canSendMessage($sender)) {
            return new MessageDeliveryFailure($channel->name, MessageDeliveryFailureReason::CannotSendToChannel);
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

        return null;
    }

    private function deliverToClient(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): ?MessageDeliveryFailure {
        $recipient = $this->clients->findByNickname($target);

        if ($recipient === null) {
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::TargetNotFound);
        }

        $connection = $this->clients->connectionFor($recipient);

        if ($connection === null) {
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::TargetNotFound);
        }

        $connection->send(new Message(
            command: $command,
            parameters: [$recipient->nickname ?? $target, $text],
            source: $sender->nickname,
        ));

        return null;
    }
}
