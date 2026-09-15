<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\DeferredFuture;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnection;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\ClientSocket;
use Psr\Log\LoggerInterface;
use Throwable;

final class AmpClientConnectionSupervisor implements ClientConnectionSupervisor
{
    /** @var array<int, ClientConnection> */
    private array $connections = [];

    private bool $accepting = true;

    /** @var null|DeferredFuture<void> */
    private ?DeferredFuture $stopped = null;

    public function __construct(
        private readonly ClientConnectionFactory $connectionFactory,
        private readonly ServerName $serverName,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(ClientSocket $socket): void
    {
        if (! $this->accepting) {
            $socket->close();

            return;
        }

        $connectionId = null;

        try {
            $connection = $this->connectionFactory->create($socket);
            $connectionId = spl_object_id($connection);
            $this->connections[$connectionId] = $connection;

            $connection->run();
        } catch (Throwable $exception) {
            if ($connectionId === null) {
                $socket->close();
            }

            $this->logger->error(
                'IRC client connection failed.',
                ['exception' => $exception],
            );
        } finally {
            if ($connectionId !== null) {
                unset($this->connections[$connectionId]);
            }

            $this->completeStopWhenIdle();
        }
    }

    public function stopAll(string $reason, bool $notifyClients): void
    {
        $this->accepting = false;

        if ($this->connections === []) {
            return;
        }

        $this->stopped ??= new DeferredFuture();

        foreach ($this->connections as $connection) {
            if ($notifyClients) {
                $connection->send(new Message(
                    command: 'ERROR',
                    parameters: [$reason],
                    source: $this->serverName->value,
                ));
            }

            $connection->close($reason);
        }

        $this->stopped->getFuture()->await();
    }

    private function completeStopWhenIdle(): void
    {
        if ($this->connections !== [] || $this->stopped === null || $this->stopped->isComplete()) {
            return;
        }

        $this->stopped->complete();
    }
}
