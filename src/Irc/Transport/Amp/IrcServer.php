<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\Future;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnection;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use Psr\Log\LoggerInterface;
use Throwable;

use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitAll;

final class IrcServer
{
    /** @var array<int, array{connection: ClientConnection, task: Future<void>}> */
    private array $runningConnections = [];

    private bool $shutdownRequested = false;

    private bool $listenersClosed = false;

    public function __construct(
        private readonly ClientListenerCollection $listeners,
        private readonly ClientConnectionFactory $connections,
        private readonly ShutdownSignalListener $shutdownSignals,
        private readonly ServerName $serverName,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        $this->shutdownSignals->start($this->requestShutdown(...));
        $listenerTasks = [];

        try {
            foreach ($this->listeners->all() as $listener) {
                $listenerTasks[] = async($this->acceptConnections(...), $listener);
            }

            await($listenerTasks);
        } finally {
            $this->shutdownSignals->stop();
            $this->closeListeners();
            awaitAll($listenerTasks);
            $this->stopConnections();
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
            $this->startConnection($socket);
        }
    }

    private function startConnection(ClientSocket $socket): void
    {
        $connection = $this->connections->create($socket);
        $connectionId = spl_object_id($connection);

        $task = async($connection->run(...))
            ->catch(function (Throwable $exception): void {
                $this->logger->error(
                    'IRC client connection failed.',
                    ['exception' => $exception],
                );
            })
            ->finally(function () use ($connectionId): void {
                unset($this->runningConnections[$connectionId]);
            });

        $this->runningConnections[$connectionId] = [
            'connection' => $connection,
            'task' => $task,
        ];
    }

    private function stopConnections(): void
    {
        $runningConnections = $this->runningConnections;
        $reason = $this->shutdownRequested ? 'Server shutting down' : 'Server stopped';

        foreach ($runningConnections as $runningConnection) {
            if ($this->shutdownRequested) {
                $runningConnection['connection']->send(new Message(
                    command: 'ERROR',
                    parameters: [$reason],
                    source: $this->serverName->value,
                ));
            }

            $runningConnection['connection']->close($reason);
        }

        foreach ($runningConnections as $runningConnection) {
            $runningConnection['task']->await();
        }
    }
}
