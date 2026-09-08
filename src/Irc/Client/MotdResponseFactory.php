<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class MotdResponseFactory
{
    public function __construct(
        private ServerName $serverName,
        private Motd $motd,
        private NumericResponseFactory $responses,
    ) {}

    /** @return list<Message> */
    public function createMotdResponses(string $target): array
    {
        if ($this->motd->isEmpty()) {
            return [
                $this->responses->create(
                    code: ResponseCode::NoMotd,
                    target: $target,
                ),
            ];
        }

        $messages = [
            $this->responses->create(
                code: ResponseCode::MotdStart,
                target: $target,
                text: "- {$this->serverName->value} Message of the day -",
            ),
        ];

        foreach ($this->motd->lines as $line) {
            $messages[] = $this->responses->create(
                code: ResponseCode::MotdLine,
                target: $target,
                text: "- {$line}",
            );
        }

        $messages[] = $this->responses->create(
            code: ResponseCode::EndOfMotd,
            target: $target,
        );

        return $messages;
    }
}
