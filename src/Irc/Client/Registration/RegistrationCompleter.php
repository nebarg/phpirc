<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Registration;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Transport\ConnectionStatistics;

final readonly class RegistrationCompleter
{
    public function __construct(
        private RegistrationWelcome $welcome,
        private ConnectionStatistics $statistics,
    ) {}

    public function completeIfReady(CommandContext $context): void
    {
        $nickname = $context->client->nickname;

        if ($nickname === null || ! $context->client->completeRegistrationIfReady()) {
            return;
        }

        $this->statistics->clientRegistered();
        $this->welcome->send($context->connection, $nickname);
    }
}
