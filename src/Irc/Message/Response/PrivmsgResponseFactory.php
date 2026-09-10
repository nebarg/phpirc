<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message\Response;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Message\MessageDeliveryFailure;
use PhpIrc\Irc\Message\MessageDeliveryFailureReason;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class PrivmsgResponseFactory
{
    public function __construct(
        private NumericErrorResponseFactory $errors,
        private AwayResponseFactory $awayResponses,
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

    public function createRecipientAwayResponse(string $target, Client $recipient): Message
    {
        return $this->awayResponses->createClientIsAwayResponse($target, $recipient);
    }
}
