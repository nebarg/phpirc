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

    public function deliver(
        Client $sender,
        string $command,
        string $targets,
        string $text,
    ): MessageDeliveryReport {
        $failures = [];
        $awayRecipients = [];

        foreach (explode(',', $targets) as $target) {
            $report = match ($this->targets->classify($target)) {
                TargetType::Channel => $this->deliverToChannel($sender, $command, $target, $text),
                TargetType::Nickname => $this->deliverToClient($sender, $command, $target, $text),
            };

            array_push($failures, ...$report->failures);
            array_push($awayRecipients, ...$report->awayRecipients);
        }

        return new MessageDeliveryReport(
            failures: $failures,
            awayRecipients: $awayRecipients,
        );
    }

    private function deliverToChannel(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): MessageDeliveryReport {
        $channel = $this->channels->find($target);

        if ($channel === null) {
            return new MessageDeliveryReport(failures: [
                new MessageDeliveryFailure($target, MessageDeliveryFailureReason::CannotSendToChannel),
            ]);
        }

        if ($this->channelAccess->canSendMessage($channel, $sender) !== ChannelPermission::Allowed) {
            return new MessageDeliveryReport(failures: [
                new MessageDeliveryFailure($channel->name, MessageDeliveryFailureReason::CannotSendToChannel),
            ]);
        }

        $this->broadcaster->broadcastExcept(
            $channel,
            $this->createMessage($sender, $command, $channel->name, $text),
            $sender,
        );

        return new MessageDeliveryReport();
    }

    private function deliverToClient(
        Client $sender,
        string $command,
        string $target,
        string $text,
    ): MessageDeliveryReport {
        $recipient = $this->clients->findByNickname($target);

        if ($recipient === null) {
            return new MessageDeliveryReport(failures: [
                new MessageDeliveryFailure($target, MessageDeliveryFailureReason::NoSuchNickname),
            ]);
        }

        $connection = $this->clients->connectionFor($recipient);

        if ($connection === null) {
            return new MessageDeliveryReport(failures: [
                new MessageDeliveryFailure($target, MessageDeliveryFailureReason::NoSuchNickname),
            ]);
        }

        $connection->send(
            $this->createMessage($sender, $command, $recipient->nickname ?? $target, $text),
        );

        return new MessageDeliveryReport(
            awayRecipients: $recipient->isAway() ? [$recipient] : [],
        );
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
