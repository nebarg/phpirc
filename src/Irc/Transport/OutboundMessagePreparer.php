<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use Psr\Log\LoggerInterface;

final readonly class OutboundMessagePreparer
{
    public function __construct(
        private MessageEncoder $encoder,
        private LoggerInterface $logger,
    ) {}

    public function prepare(Message $message): ?string
    {
        try {
            $encoded = $this->encoder->encodeWithSize($message);
        } catch (InvalidMessageException $exception) {
            $this->logger->error(
                'Refused to send an invalid IRC message.',
                [
                    'command' => $message->command,
                    'exception' => $exception,
                ],
            );

            return null;
        }

        if ($encoded->mainSectionBytes <= MessageSize::MAX_BYTES) {
            return $encoded->bytes;
        }

        $this->logger->error(
            'Refused to send an oversized IRC message.',
            [
                'command' => $message->command,
                'bytes' => $encoded->mainSectionBytes,
                'limit' => MessageSize::MAX_BYTES,
            ],
        );

        return null;
    }
}
