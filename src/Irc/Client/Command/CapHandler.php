<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Client\Response\CapabilityResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;

final readonly class CapHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private CapabilityResponseFactory $capabilityResponses,
        private RegistrationCompleter $registration,
    ) {}

    public function command(): string
    {
        return 'CAP';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $subcommand = strtoupper($message->parameter(0));

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
        $context->connection->send(
            $this->capabilityResponses->createSupportedCapabilitiesResponse(
                $context->responseTarget(),
                '',
            ),
        );
    }

    private function listEnabledCapabilities(CommandContext $context): void
    {
        $context->connection->send(
            $this->capabilityResponses->createEnabledCapabilitiesResponse(
                $context->responseTarget(),
                '',
            ),
        );
    }

    private function rejectRequestedCapabilities(
        CommandContext $context,
        Message $message,
    ): void {
        $context->client->registration->suspendForCapabilityNegotiation();
        $context->connection->send(
            $this->capabilityResponses->createRejectedCapabilitiesResponse(
                $context->responseTarget(),
                $message->parameter(1),
            ),
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
            $this->capabilityResponses->createInvalidSubcommandResponse(
                $context->responseTarget(),
                $subcommand,
            ),
        );
    }
}
