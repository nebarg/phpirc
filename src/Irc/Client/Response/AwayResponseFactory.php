<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Response;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class AwayResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
        private MessageTextLimiter $messageText,
    ) {}

    public function createClientIsAwayResponse(string $target, Client $client): Message
    {
        $nickname = $client->nickname;
        $awayMessage = $client->awayMessage;

        if ($nickname === null || $awayMessage === null) {
            throw new LogicException('An away response requires a nicknamed, away client.');
        }

        return $this->messageText->limit(
            $this->responses->create(
                code: ResponseCode::Away,
                target: $target,
                parameters: [$nickname],
                text: $awayMessage,
            ),
        );
    }

    public function createNoLongerAwayResponse(string $target): Message
    {
        return $this->responses->create(
            code: ResponseCode::Unaway,
            target: $target,
        );
    }

    public function createMarkedAwayResponse(string $target): Message
    {
        return $this->responses->create(
            code: ResponseCode::NowAway,
            target: $target,
        );
    }
}
