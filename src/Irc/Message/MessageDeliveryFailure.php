<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

final readonly class MessageDeliveryFailure
{
    public function __construct(
        public string $target,
        public MessageDeliveryFailureReason $reason,
    ) {}
}
