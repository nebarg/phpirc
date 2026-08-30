<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\PingHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class PingHandlerTest extends TestCase
{
    #[Test]
    public function it_handles_the_ping_command(): void
    {
        $this->assertSame('PING', $this->handler()->command());
    }

    #[Test]
    public function it_responds_with_a_pong_containing_the_same_token(): void
    {
        $connection = new RecordingConnection();

        $this->handler()->handle(
            $this->context($connection),
            $this->message(['opaque-token']),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('PONG', $connection->messages[0]->command);
        $this->assertSame(
            ['irc.test', 'opaque-token'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_sends_no_origin_when_the_token_is_missing(): void
    {
        $connection = new RecordingConnection();

        $this->handler()->handle($this->context($connection), $this->message([]));

        $this->assertNoOriginResponse($connection);
    }

    #[Test]
    public function it_sends_no_origin_when_the_token_is_empty(): void
    {
        $connection = new RecordingConnection();

        $this->handler()->handle($this->context($connection), $this->message(['']));

        $this->assertNoOriginResponse($connection);
    }

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(
            command: 'PING',
            parameters: $parameters,
        );
    }

    private function assertNoOriginResponse(RecordingConnection $connection): void
    {
        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('409', $connection->messages[0]->command);
        $this->assertSame(
            ['*', 'No origin specified'],
            $connection->messages[0]->parameters,
        );
    }

    private function handler(): PingHandler
    {
        $serverName = new ServerName('irc.test');

        return new PingHandler($serverName, new NumericResponseFactory($serverName));
    }

    private function context(RecordingConnection $connection): CommandContext
    {
        return new CommandContext($connection, new Client());
    }
}
