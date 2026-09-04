<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Flood;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\Connection;
use PhpIrc\Irc\Transport\Flood\MessageRateLimiter;
use PhpIrc\Irc\Transport\Flood\RateLimitedMessageHandler;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\Support\Irc\Transport\Time\ManualMonotonicClock;
use Tests\TestCase;

final class RateLimitedMessageHandlerTest extends TestCase
{
    #[Test]
    public function it_delegates_messages_within_the_limit(): void
    {
        $next = new RecordingMessageHandler();
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = new Message(command: 'PING');

        $this->handler($next)->handle($context, $message);

        $this->assertSame([$message], $next->messages);
        $this->assertSame([], $connection->messages);
        $this->assertSame(0, $connection->closeCalls);
    }

    #[Test]
    public function it_reports_excess_flood_and_closes_without_dispatching_the_message(): void
    {
        $next = new RecordingMessageHandler();
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $handler = $this->handler($next);
        $first = new Message(command: 'PING');
        $excess = new Message(command: 'PRIVMSG');

        $handler->handle($context, $first);
        $handler->handle($context, $excess);

        $this->assertSame([$first], $next->messages);
        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('ERROR', $connection->messages[0]->command);
        $this->assertSame(['Excess flood'], $connection->messages[0]->parameters);
        $this->assertSame(['Excess flood'], $connection->closeReasons);
    }

    #[Test]
    public function it_still_closes_when_reporting_excess_flood_fails(): void
    {
        $next = new RecordingMessageHandler();
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new ClientSocketException('Write failed.'));
        $connection
            ->expects($this->once())
            ->method('close')
            ->with('Excess flood');
        $context = new CommandContext($connection, new Client());
        $handler = $this->handler($next);

        $handler->handle($context, new Message(command: 'PING'));
        $handler->handle($context, new Message(command: 'PRIVMSG'));

        $this->assertCount(1, $next->messages);
    }

    private function handler(RecordingMessageHandler $next): RateLimitedMessageHandler
    {
        return new RateLimitedMessageHandler(
            next: $next,
            limiter: new MessageRateLimiter(
                clock: new ManualMonotonicClock(),
                config: new FloodProtectionConfig(
                    burstMessages: 1,
                    messagesPerSecond: 1,
                ),
            ),
            serverName: new ServerName('irc.test'),
        );
    }
}
