<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command\Fallback;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\Fallback\UnknownCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class UnknownCommandHandlerTest extends TestCase
{
    #[Test]
    public function it_sends_an_unknown_command_numeric(): void
    {
        $connection = new RecordingConnection();

        $responseFactory = new NumericResponseFactory(new ServerName('irc.test'));

        new UnknownCommandHandler($responseFactory)->handle(
            new CommandContext($connection, new Client()),
            new Message(
                command: 'WHATEVER',
                parameters: ['ignored'],
            ),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('421', $connection->messages[0]->command);
        $this->assertSame(
            ['*', 'WHATEVER', 'Unknown command'],
            $connection->messages[0]->parameters,
        );
    }
}
