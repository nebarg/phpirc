<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\InvalidMessageException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageSize;
use Psr\Log\LoggerInterface;

final readonly class OutboundMessageGuard
{
    public function __construct(
        private MessageSize $messageSize,
        private LoggerInterface $logger,
    ) {}

    public function allows(Message $message): bool
    {
        try {
            $bytes = $this->messageSize->inBytes($message);
        } catch (InvalidMessageException $exception) {
            $this->logger->error(
                'Refused to send an invalid IRC message.',
                [
                    'command' => $message->command,
                    'exception' => $exception,
                ],
            );

            return false;
        }

        if ($bytes <= MessageSize::MAX_BYTES) {
            return true;
        }

        $this->logger->error(
            'Refused to send an oversized IRC message.',
            [
                'command' => $message->command,
                'bytes' => $bytes,
                'limit' => MessageSize::MAX_BYTES,
            ],
        );

        return false;
    }
}
