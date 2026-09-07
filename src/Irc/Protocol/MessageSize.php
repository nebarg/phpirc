<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

/** Measures the encoded main section, including its terminating CRLF and excluding tags. */
final readonly class MessageSize
{
    public const int MAX_BYTES_WITHOUT_TERMINATOR = 510;

    public const int MAX_BYTES = 512;

    public function __construct(
        private MessageEncoder $encoder,
    ) {}

    public function inBytes(Message $message): int
    {
        $messageWithoutTags = new Message(
            command: $message->command,
            parameters: $message->parameters,
            source: $message->source,
        );

        return strlen($this->encoder->encode($messageWithoutTags));
    }

    public function fits(Message $message): bool
    {
        return $this->inBytes($message) <= self::MAX_BYTES;
    }
}
