<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class CapHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private ServerName $serverName,
        private NumericResponseFactory $responses,
        private RegistrationCompleter $registration,
    ) {}

    public function command(): string
    {
        return 'CAP';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $subcommand = strtoupper($message->parameters[0] ?? '');

        switch ($subcommand) {
            case 'LS':
                $this->listSupportedCapabilities($context);
                break;

            case 'LIST':
                $this->listEnabledCapabilities($context);
                break;

            case 'REQ':
                $this->rejectRequestedCapabilities($context, $message);
                break;

            case 'END':
                $this->endNegotiation($context);
                break;

            default:
                $this->rejectInvalidSubcommand($context, $subcommand);
        }
    }

    private function listSupportedCapabilities(CommandContext $context): void
    {
        $context->client->registration->suspendForCapabilityNegotiation();
        $this->sendCapabilityReply($context, 'LS', '');
    }

    private function listEnabledCapabilities(CommandContext $context): void
    {
        $this->sendCapabilityReply($context, 'LIST', '');
    }

    private function rejectRequestedCapabilities(
        CommandContext $context,
        Message $message,
    ): void {
        $context->client->registration->suspendForCapabilityNegotiation();
        $this->sendCapabilityReply(
            $context,
            'NAK',
            $message->parameters[1] ?? '',
        );
    }

    private function endNegotiation(CommandContext $context): void
    {
        if ($context->client->registration->isComplete()) {
            return;
        }

        $context->client->registration->resumeAfterCapabilityNegotiation();
        $this->registration->completeIfReady($context);
    }

    private function rejectInvalidSubcommand(
        CommandContext $context,
        string $subcommand,
    ): void {
        $context->connection->send(
            $this->responses->create(
                code: ResponseCode::InvalidCapCommand,
                target: $context->responseTarget(),
                parameters: [$subcommand === '' ? '*' : $subcommand],
            ),
        );
    }

    private function sendCapabilityReply(
        CommandContext $context,
        string $subcommand,
        string $capabilities,
    ): void {
        $context->connection->send(
            new Message(
                command: $this->command(),
                parameters: [
                    $context->responseTarget(),
                    $subcommand,
                    $capabilities,
                ],
                source: $this->serverName->value,
            ),
        );
    }
}
