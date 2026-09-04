<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\QuitHandler;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class QuitHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>, string}> */
    public static function reasons(): iterable
    {
        yield 'supplied reason' => [['Gone for lunch'], 'Quit: Gone for lunch'];
        yield 'missing reason' => [[], 'Quit: '];
        yield 'empty reason' => [[''], 'Quit: '];
    }

    #[Test]
    public function it_handles_quit_before_or_after_registration(): void
    {
        $handler = $this->handler();

        $this->assertSame('QUIT', $handler->command());
        $this->assertInstanceOf(PreRegistrationCommandHandler::class, $handler);
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('reasons')]
    public function it_sends_a_closing_error_and_closes_with_the_quit_reason(
        array $parameters,
        string $reason,
    ): void {
        $handler = $this->handler();
        $client = new Client();
        $client->setNickname('John');
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'QUIT', parameters: $parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('ERROR', $connection->messages[0]->command);
        $this->assertSame(
            ["Closing Link: John ({$reason})"],
            $connection->messages[0]->parameters,
        );
        $this->assertSame([$reason], $connection->closeReasons);
    }

    #[Test]
    public function it_closes_an_unregistered_client_without_notifying_anyone(): void
    {
        $handler = $this->handler();
        $client = new Client();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'QUIT'),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame(
            ['Closing Link: * (Quit: )'],
            $connection->messages[0]->parameters,
        );
        $this->assertSame(1, $connection->closeCalls);
        $this->assertSame(['Quit: '], $connection->closeReasons);
    }

    private function handler(): QuitHandler
    {
        return new QuitHandler(
            serverName: new ServerName('irc.test'),
        );
    }
}
