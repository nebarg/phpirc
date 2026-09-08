<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageParser;

final class MessageCodec
{
    public function __construct(
        private readonly LineBuffer $buffer,
        private readonly MessageParser $parser,
        private readonly OutboundMessagePreparer $outboundMessages,
    ) {}

    /** @return iterable<Message> */
    public function decode(string $bytes): iterable
    {
        foreach ($this->buffer->push($bytes) as $line) {
            try {
                yield $this->parser->parse($line);
            } catch (InvalidMessageException) {
                continue;
            }
        }
    }

    public function encode(Message $message): ?string
    {
        return $this->outboundMessages->prepare($message);
    }
}
