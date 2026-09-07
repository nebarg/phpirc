<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class MessageTextLimiter
{
    public function __construct(
        private MessageSize $messageSize,
        private ByteStringTruncator $strings,
    ) {}

    /**
     * Truncates only the final parameter when the encoded main section exceeds
     * the IRC message-size limit.
     *
     * @throws InvalidMessageException
     */
    public function limit(Message $message): Message
    {
        if ($this->messageSize->fits($message)) {
            return $message;
        }

        if ($message->parameters === []) {
            throw new InvalidMessageException('Oversized message does not have a text parameter to truncate.');
        }

        $textPosition = count($message->parameters) - 1;
        $text = $message->parameters[$textPosition];
        $fixedBytes = $this->messageSize->inBytes($message) - strlen($text);
        $availableTextBytes = MessageSize::MAX_BYTES - $fixedBytes;

        if ($availableTextBytes < 0) {
            throw new InvalidMessageException('Message fields exceed the IRC message-size limit.');
        }

        $parameters = $message->parameters;
        $parameters[$textPosition] = $this->strings->truncate($text, $availableTextBytes);

        $limited = new Message(
            command: $message->command,
            parameters: $parameters,
            source: $message->source,
            tags: $message->tags,
        );

        if (! $this->messageSize->fits($limited)) {
            throw new InvalidMessageException('Message text could not be truncated to the IRC message-size limit.');
        }

        return $limited;
    }
}
