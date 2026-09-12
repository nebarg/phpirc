<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Capability\Capability;
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
                $this->changeRequestedCapabilities($context, $message);
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
                $this->capabilityNames(Capability::cases()),
            ),
        );
    }

    private function listEnabledCapabilities(CommandContext $context): void
    {
        $context->connection->send(
            $this->capabilityResponses->createEnabledCapabilitiesResponse(
                $context->responseTarget(),
                $this->capabilityNames($context->client->capabilities->all()),
            ),
        );
    }

    private function changeRequestedCapabilities(
        CommandContext $context,
        Message $message,
    ): void {
        $context->client->registration->suspendForCapabilityNegotiation();

        $request = trim($message->parameter(1));
        $changes = $this->parseChanges($request);

        if ($changes === null) {
            $context->connection->send(
                $this->capabilityResponses->createRejectedCapabilitiesResponse(
                    $context->responseTarget(),
                    $request,
                ),
            );

            return;
        }

        $context->connection->send(
            $this->capabilityResponses->createAcknowledgedCapabilitiesResponse(
                $context->responseTarget(),
                $request,
            ),
        );

        foreach ($changes as [$capability, $enable]) {
            if ($enable) {
                $context->client->capabilities->enable($capability);
                continue;
            }

            $context->client->capabilities->disable($capability);
        }
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

    /**
     * @return null|list<array{Capability, bool}>
     */
    private function parseChanges(string $request): ?array
    {
        if ($request === '') {
            return null;
        }

        $changes = [];

        foreach (preg_split('/ +/', $request) ?: [] as $requestedCapability) {
            $enable = ! str_starts_with($requestedCapability, '-');
            $name = $enable ? $requestedCapability : substr($requestedCapability, 1);
            $capability = Capability::tryFrom($name);

            if ($capability === null) {
                return null;
            }

            $changes[] = [$capability, $enable];
        }

        return $changes;
    }

    /** @param list<Capability> $capabilities */
    private function capabilityNames(array $capabilities): string
    {
        return implode(
            ' ',
            array_map(
                static fn (Capability $capability): string => $capability->value,
                $capabilities,
            ),
        );
    }
}
