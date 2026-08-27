<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\PingHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Transport\RecordingConnection;

final class PingHandlerTest extends IntegrationTestCase
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
            $connection,
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

        $this->handler()->handle($connection, $this->message([]));

        $this->assertNoOriginResponse($connection);
    }

    #[Test]
    public function it_sends_no_origin_when_the_token_is_empty(): void
    {
        $connection = new RecordingConnection();

        $this->handler()->handle($connection, $this->message(['']));

        $this->assertNoOriginResponse($connection);
    }

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(
            tags: [],
            source: null,
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
        return new PingHandler(new ServerName('irc.test'));
    }
}
