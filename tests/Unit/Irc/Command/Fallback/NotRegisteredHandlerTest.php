<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command\Fallback;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\Fallback\NotRegisteredHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class NotRegisteredHandlerTest extends TestCase
{
    #[Test]
    public function it_sends_a_not_registered_numeric_to_an_unnamed_client(): void
    {
        $connection = new RecordingConnection();

        $this->handler()->handle(
            new CommandContext($connection, new Client()),
            new Message(command: 'JOIN', parameters: ['#php']),
        );

        $this->assertResponse($connection, '*');
    }

    #[Test]
    public function it_targets_the_clients_nickname_when_available(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');

        $this->handler()->handle(
            new CommandContext($connection, $client),
            new Message(command: 'JOIN', parameters: ['#php']),
        );

        $this->assertResponse($connection, 'John');
    }

    private function handler(): NotRegisteredHandler
    {
        return new NotRegisteredHandler(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }

    private function assertResponse(RecordingConnection $connection, string $target): void
    {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('451', $connection->messages[0]->command);
        $this->assertSame(
            [$target, 'You have not registered'],
            $connection->messages[0]->parameters,
        );
    }
}
