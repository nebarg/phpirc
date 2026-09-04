<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Keepalive;

use PhpIrc\Irc\Config\KeepaliveConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\Connection;
use PhpIrc\Irc\Transport\Timer\TimerScheduler;

final class ConnectionKeepalive
{
    private ?Connection $connection = null;

    private ?string $timerId = null;

    private ?string $pendingToken = null;

    private int $tokenSequence = 0;

    public function __construct(
        private readonly TimerScheduler $timers,
        private readonly KeepaliveConfig $config,
        private readonly ServerName $serverName,
    ) {}

    public function start(Connection $connection): void
    {
        $this->connection = $connection;
        $this->clearPendingToken();
        $this->schedulePing();
    }

    public function activityReceived(): void
    {
        if ($this->connection === null || $this->pendingToken !== null) {
            return;
        }

        $this->schedulePing();
    }

    public function pongReceived(string $token): void
    {
        if ($this->pendingToken === null || ! hash_equals($this->pendingToken, $token)) {
            return;
        }

        $this->clearPendingToken();
        $this->schedulePing();
    }

    public function stop(): void
    {
        $this->cancelTimer();
        $this->clearPendingToken();
        $this->connection = null;
    }

    private function schedulePing(): void
    {
        $this->cancelTimer();

        $this->timerId = $this->timers->delay(
            $this->config->pingIntervalSeconds,
            $this->sendPing(...),
        );
    }

    private function sendPing(): void
    {
        $this->timerId = null;

        $connection = $this->connection;

        if ($connection === null) {
            return;
        }

        $token = sprintf('%s-%d', $this->serverName->value, ++$this->tokenSequence);
        $this->pendingToken = $token;
        $this->timerId = $this->timers->delay(
            $this->config->pongTimeoutSeconds,
            $this->pingTimedOut(...),
        );

        try {
            $connection->send(new Message(
                command: 'PING',
                parameters: [$token],
                source: $this->serverName->value,
            ));
        } catch (ClientSocketException) {
            $this->cancelTimer();
            $this->clearPendingToken();
            $connection->close();
        }
    }

    private function pingTimedOut(): void
    {
        $this->timerId = null;
        $this->clearPendingToken();

        $connection = $this->connection;

        if ($connection === null) {
            return;
        }

        try {
            $connection->send(new Message(
                command: 'ERROR',
                parameters: ['Ping timeout'],
                source: $this->serverName->value,
            ));
        } catch (ClientSocketException) {
            // The socket is already unusable, but the connection still needs closing.
        } finally {
            $connection->close('Ping timeout');
        }
    }

    private function clearPendingToken(): void
    {
        $this->pendingToken = null;
    }

    private function cancelTimer(): void
    {
        if ($this->timerId === null) {
            return;
        }

        $this->timers->cancel($this->timerId);
        $this->timerId = null;
    }
}
