<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\Future;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnection;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\Signal\ShutdownSignalListener;
use Psr\Log\LoggerInterface;
use Throwable;

use function Amp\async;

final class IrcServer
{
    /** @var array<int, array{connection: ClientConnection, task: Future<void>}> */
    private array $runningConnections = [];

    private bool $shutdownRequested = false;

    private bool $listenerClosed = false;

    public function __construct(
        private readonly ClientListener $listener,
        private readonly ClientConnectionFactory $connections,
        private readonly ShutdownSignalListener $shutdownSignals,
        private readonly ServerName $serverName,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        $this->shutdownSignals->start($this->requestShutdown(...));

        try {
            while (($socket = $this->listener->accept()) !== null) {
                $this->startConnection($socket);
            }
        } finally {
            $this->shutdownSignals->stop();
            $this->closeListener();
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
        $this->closeListener();
    }

    private function closeListener(): void
    {
        if ($this->listenerClosed) {
            return;
        }

        $this->listenerClosed = true;
        $this->listener->close();
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
