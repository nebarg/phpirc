<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use PhpIrc\Irc\Transport\Websocket\WebsocketServer;
use Psr\Log\LoggerInterface;

use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitAll;

final class IrcServer
{
    private bool $shutdownRequested = false;

    private bool $listenersClosed = false;

    public function __construct(
        private readonly ClientListenerCollection $listeners,
        private readonly ClientConnectionSupervisor $connections,
        private readonly WebsocketServer $websockets,
        private readonly ShutdownSignalListener $shutdownSignals,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        $this->shutdownSignals->start($this->requestShutdown(...));
        $listenerTasks = [];

        try {
            $this->websockets->start();

            foreach ($this->listeners->all() as $listener) {
                $listenerTasks[] = async($this->acceptConnections(...), $listener);
            }

            await($listenerTasks);
        } finally {
            $this->shutdownSignals->stop();
            $this->closeListeners();
            awaitAll($listenerTasks);

            try {
                $this->connections->stopAll(
                    reason: $this->shutdownRequested ? 'Server shutting down' : 'Server stopped',
                    notifyClients: $this->shutdownRequested,
                );
            } finally {
                $this->websockets->stop();
            }
        }
    }

    private function requestShutdown(): void
    {
        if ($this->shutdownRequested) {
            return;
        }

        $this->shutdownRequested = true;
        $this->logger->info('IRC server shutdown requested.');
        $this->closeListeners();
    }

    private function closeListeners(): void
    {
        if ($this->listenersClosed) {
            return;
        }

        $this->listenersClosed = true;

        foreach ($this->listeners->all() as $listener) {
            $listener->close();
        }
    }

    private function acceptConnections(ClientListener $listener): void
    {
        while (($socket = $listener->accept()) !== null) {
            async($this->connections->run(...), $socket)->ignore();
        }
    }
}
