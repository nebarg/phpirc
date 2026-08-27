<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\UnknownCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Transport\RecordingConnection;

final class UnknownCommandHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function it_sends_an_unknown_command_numeric(): void
    {
        $connection = new RecordingConnection;

        new UnknownCommandHandler(new ServerName('irc.test'))->handle(
            $connection,
            new Message(
                tags: [],
                source: null,
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
