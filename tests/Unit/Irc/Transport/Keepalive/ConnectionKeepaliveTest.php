<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Keepalive;

use PhpIrc\Irc\Config\KeepaliveConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\Connection;
use PhpIrc\Irc\Transport\Keepalive\ConnectionKeepalive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\Support\Irc\Transport\Timer\ManualTimerScheduler;
use Tests\TestCase;

final class ConnectionKeepaliveTest extends TestCase
{
    #[Test]
    public function it_sends_a_ping_after_the_connection_is_idle(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();

        $keepalive->start($connection);
        $timers->runNext();

        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('PING', $connection->messages[0]->command);
        $this->assertSame(['irc.test-1'], $connection->messages[0]->parameters);
        $this->assertSame([30.0], $timers->pendingDelays());
    }

    #[Test]
    public function it_restarts_the_idle_timer_when_activity_is_received(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();

        $keepalive->start($connection);
        $keepalive->activityReceived();

        $this->assertSame([120.0, 120.0], $timers->scheduledDelays);
        $this->assertCount(1, $timers->cancelledTimers);
        $this->assertSame([120.0], $timers->pendingDelays());
    }

    #[Test]
    public function it_accepts_a_matching_pong_and_returns_to_idle(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();
        $keepalive->start($connection);
        $timers->runNext();

        $keepalive->pongReceived('irc.test-1');

        $this->assertSame([120.0], $timers->pendingDelays());
        $this->assertSame(0, $connection->closeCalls);

        $timers->runNext();

        $this->assertSame(['irc.test-2'], $connection->messages[1]->parameters);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPongTokens(): iterable
    {
        yield 'empty token' => [''];
        yield 'different token' => ['irc.test-wrong'];
    }

    #[Test]
    #[DataProvider('invalidPongTokens')]
    public function it_does_not_accept_an_invalid_pong(string $token): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();
        $keepalive->start($connection);
        $timers->runNext();

        $keepalive->pongReceived($token);
        $timers->runNext();

        $this->assertSame(['Ping timeout'], $connection->closeReasons);
    }

    #[Test]
    public function ordinary_activity_does_not_satisfy_an_outstanding_ping(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();
        $keepalive->start($connection);
        $timers->runNext();

        $keepalive->activityReceived();
        $timers->runNext();

        $this->assertSame(['Ping timeout'], $connection->closeReasons);
        $this->assertSame([120.0, 30.0], $timers->scheduledDelays);
    }

    #[Test]
    public function it_reports_the_ping_timeout_before_closing_the_connection(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();
        $keepalive->start($connection);
        $timers->runNext();

        $timers->runNext();

        $this->assertCount(2, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[1]->source);
        $this->assertSame('ERROR', $connection->messages[1]->command);
        $this->assertSame(['Ping timeout'], $connection->messages[1]->parameters);
        $this->assertSame(['Ping timeout'], $connection->closeReasons);
    }

    #[Test]
    public function it_stops_and_cancels_its_active_timer_idempotently(): void
    {
        [$keepalive, $timers, $connection] = $this->keepalive();
        $keepalive->start($connection);

        $keepalive->stop();
        $keepalive->stop();

        $this->assertSame(0, $timers->pendingCount());
        $this->assertCount(1, $timers->cancelledTimers);
    }

    #[Test]
    public function it_closes_the_connection_when_sending_the_ping_fails(): void
    {
        $timers = new ManualTimerScheduler();
        $connection = new class implements Connection {
            public int $closeCalls = 0;

            public function send(Message $message): void
            {
                throw new ClientSocketException('Write failed.');
            }

            public function close(string $reason = 'Connection closed'): void
            {
                $this->closeCalls++;
            }

            public function pongReceived(string $token): void {}
        };
        $keepalive = $this->createKeepalive($timers);
        $keepalive->start($connection);

        $timers->runNext();

        $this->assertSame(1, $connection->closeCalls);
        $this->assertSame(0, $timers->pendingCount());
    }

    #[Test]
    public function it_still_closes_when_reporting_the_timeout_fails(): void
    {
        $timers = new ManualTimerScheduler();
        $connection = new class implements Connection {
            public int $sendCalls = 0;

            public int $closeCalls = 0;

            public function send(Message $message): void
            {
                if (++$this->sendCalls === 2) {
                    throw new ClientSocketException('Write failed.');
                }
            }

            public function close(string $reason = 'Connection closed'): void
            {
                $this->closeCalls++;
            }

            public function pongReceived(string $token): void {}
        };
        $keepalive = $this->createKeepalive($timers);
        $keepalive->start($connection);
        $timers->runNext();

        $timers->runNext();

        $this->assertSame(2, $connection->sendCalls);
        $this->assertSame(1, $connection->closeCalls);
    }

    /** @return array{ConnectionKeepalive, ManualTimerScheduler, RecordingConnection} */
    private function keepalive(): array
    {
        $timers = new ManualTimerScheduler();

        return [
            $this->createKeepalive($timers),
            $timers,
            new RecordingConnection(),
        ];
    }

    private function createKeepalive(ManualTimerScheduler $timers): ConnectionKeepalive
    {
        return new ConnectionKeepalive(
            timers: $timers,
            config: new KeepaliveConfig(
                pingIntervalSeconds: 120,
                pongTimeoutSeconds: 30,
            ),
            serverName: new ServerName('irc.test'),
        );
    }
}
