<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Response;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class CapabilityResponseFactory
{
    public function __construct(
        private ServerName $serverName,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function createSupportedCapabilitiesResponse(string $target, string $capabilities): Message
    {
        return $this->createReply($target, 'LS', $capabilities);
    }

    public function createEnabledCapabilitiesResponse(string $target, string $capabilities): Message
    {
        return $this->createReply($target, 'LIST', $capabilities);
    }

    public function createRejectedCapabilitiesResponse(string $target, string $capabilities): Message
    {
        return $this->createReply($target, 'NAK', $capabilities);
    }

    public function createAcknowledgedCapabilitiesResponse(string $target, string $capabilities): Message
    {
        return $this->createReply($target, 'ACK', $capabilities);
    }

    public function createInvalidSubcommandResponse(string $target, string $subcommand): Message
    {
        return $this->errors->invalidCapabilityCommand($target, $subcommand);
    }

    private function createReply(string $target, string $subcommand, string $capabilities): Message
    {
        return new Message(
            command: 'CAP',
            parameters: [$target, $subcommand, $capabilities],
            source: $this->serverName->value,
        );
    }
}
