<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\ClientRegistry;

final class ConnectionStatistics
{
    public private(set) int $peakConnections = 0;

    public private(set) int $peakRegisteredClients = 0;

    public private(set) int $connectionsReceived = 0;

    public function __construct(
        private readonly ClientRegistry $clients,
    ) {}

    public function connectionAccepted(): void
    {
        ++$this->connectionsReceived;
        $this->peakConnections = max(
            $this->peakConnections,
            $this->clients->connectedCount(),
        );
    }

    public function clientRegistered(): void
    {
        $this->peakRegisteredClients = max(
            $this->peakRegisteredClients,
            $this->clients->registeredCount(),
        );
    }
}
