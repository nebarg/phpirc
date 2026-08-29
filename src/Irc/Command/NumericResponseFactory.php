<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class NumericResponseFactory
{
    public function __construct(
        private ServerName $serverName,
    ) {}

    /** @param list<string> $parameters */
    public function create(
        ResponseCode $code,
        ?string $target = null,
        array $parameters = [],
        ?string $text = null,
    ): Message {
        $text ??= $code->defaultText();

        if ($text !== null) {
            $parameters[] = $text;
        }

        return new Message(
            command: $code->value,
            parameters: [
                $target ?? '*',
                ...$parameters,
            ],
            source: $this->serverName->value,
        );
    }
}
