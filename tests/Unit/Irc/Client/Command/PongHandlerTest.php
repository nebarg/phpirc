<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\PongHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class PongHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function invalidTokens(): iterable
    {
        yield 'empty token' => [['']];
        yield 'missing token' => [[]];
    }

    #[Test]
    public function it_handles_pong_before_or_after_registration(): void
    {
        $handler = new PongHandler();

        $this->assertSame('PONG', $handler->command());
        $this->assertInstanceOf(PreRegistrationCommandHandler::class, $handler);
    }

    #[Test]
    public function it_passes_the_received_token_to_the_connection(): void
    {
        $connection = new RecordingConnection();

        new PongHandler()->handle(
            new CommandContext($connection, new Client()),
            new Message(command: 'PONG', parameters: ['irc.test-1']),
        );

        $this->assertSame(['irc.test-1'], $connection->pongTokens);
        $this->assertSame([], $connection->messages);
        $this->assertSame(0, $connection->closeCalls);
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('invalidTokens')]
    public function it_ignores_a_missing_or_empty_token(array $parameters): void
    {
        $connection = new RecordingConnection();

        new PongHandler()->handle(
            new CommandContext($connection, new Client()),
            new Message(command: 'PONG', parameters: $parameters),
        );

        $this->assertSame([], $connection->pongTokens);
    }
}
