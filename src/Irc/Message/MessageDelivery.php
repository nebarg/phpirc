<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PhpIrc\Irc\Protocol\Target\TargetType;

final readonly class MessageDelivery
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private ChannelBroadcaster $broadcaster,
        private TargetClassifier $targets,
        private ChannelAccessPolicy $channelAccess,
        private MessageTextLimiter $messageText,
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
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::CannotSendToChannel);
        }

        if ($this->channelAccess->checkMessageDelivery($channel, $sender) !== ChannelPermission::Allowed) {
            return new MessageDeliveryFailure($channel->name, MessageDeliveryFailureReason::CannotSendToChannel);
        }

        $this->broadcaster->broadcastExcept(
            $channel,
            $this->createMessage($sender, $command, $channel->name, $text),
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
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::NoSuchNickname);
        }

        $connection = $this->clients->connectionFor($recipient);

        if ($connection === null) {
            return new MessageDeliveryFailure($target, MessageDeliveryFailureReason::NoSuchNickname);
        }

        $connection->send(
            $this->createMessage($sender, $command, $recipient->nickname ?? $target, $text),
        );

        return null;
    }

    private function createMessage(Client $sender, string $command, string $target, string $text): Message
    {
        return $this->messageText->limit(new Message(
            command: $command,
            parameters: [$target, $text],
            source: $sender->nickname,
        ));
    }
}
