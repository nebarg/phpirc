<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class PrivmsgResponseFactory
{
    public function __construct(
        private NumericErrorResponseFactory $errors,
    ) {}

    public function createMissingRecipientResponse(string $target): Message
    {
        return $this->errors->noRecipient($target);
    }

    public function createMissingTextResponse(string $target): Message
    {
        return $this->errors->noTextToSend($target);
    }

    public function createDeliveryFailureResponse(string $target, MessageDeliveryFailure $failure): Message
    {
        return match ($failure->reason) {
            MessageDeliveryFailureReason::NoSuchNickname => $this->errors->noSuchNickname($target, $failure->target),
            MessageDeliveryFailureReason::CannotSendToChannel => $this->errors->cannotSendToChannel(
                $target,
                $failure->target,
            ),
        };
    }
}
