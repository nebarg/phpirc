<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class CapabilityResponseFactory
{
    public function __construct(
        private ServerName $serverName,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function createReply(string $target, string $subcommand, string $capabilities): Message
    {
        return new Message(
            command: 'CAP',
            parameters: [$target, $subcommand, $capabilities],
            source: $this->serverName->value,
        );
    }

    public function createInvalidSubcommandResponse(string $target, string $subcommand): Message
    {
        return $this->errors->invalidCapabilityCommand($target, $subcommand);
    }
}
