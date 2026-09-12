<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Capability;

use DateTimeZone;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageTag;
use PhpIrc\Irc\Time\WallClock;

final readonly class ServerTimeMessageTagger
{
    private DateTimeZone $utc;

    public function __construct(
        private WallClock $clock,
    ) {
        $this->utc = new DateTimeZone('UTC');
    }

    public function tag(Message $message, Client $recipient): Message
    {
        $hasTimeTag = $this->hasTimeTag($message);

        if (! $recipient->capabilities->has(Capability::ServerTime)) {
            return $hasTimeTag ? $this->withoutTimeTag($message) : $message;
        }

        if ($hasTimeTag) {
            return $message;
        }

        $timestamp = $this->clock
            ->now()
            ->setTimezone($this->utc)
            ->format('Y-m-d\TH:i:s.v\Z');

        return new Message(
            command: $message->command,
            parameters: $message->parameters,
            source: $message->source,
            tags: [...$message->tags, new MessageTag('time', $timestamp)],
        );
    }

    private function hasTimeTag(Message $message): bool
    {
        foreach ($message->tags as $tag) {
            if ($tag->name === 'time') {
                return true;
            }
        }

        return false;
    }

    private function withoutTimeTag(Message $message): Message
    {
        return new Message(
            command: $message->command,
            parameters: $message->parameters,
            source: $message->source,
            tags: array_values(array_filter(
                $message->tags,
                static fn (MessageTag $tag): bool => $tag->name !== 'time',
            )),
        );
    }
}
