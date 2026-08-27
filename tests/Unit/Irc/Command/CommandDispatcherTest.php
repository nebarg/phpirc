<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use LogicException;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Command\UnknownCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingCommandHandler;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class CommandDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_to_the_handler_for_the_message_command(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $this->unknownCommandHandler(),
        );
        $connection = new RecordingConnection();
        $message = $this->message('ping');

        $dispatcher->handle($connection, $message);

        $this->assertSame([$connection], $handler->connections);
        $this->assertSame([$message], $handler->messages);
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_uses_the_unknown_handler_when_no_command_matches(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $this->unknownCommandHandler(),
        );
        $connection = new RecordingConnection();

        $dispatcher->handle($connection, $this->message('WHATEVER'));

        $this->assertSame([], $handler->messages);
        $this->assertCount(1, $connection->messages);
        $this->assertSame('421', $connection->messages[0]->command);
        $this->assertSame(
            ['*', 'WHATEVER', 'Unknown command'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_rejects_duplicate_command_handlers_case_insensitively(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate PING handler found.');

        new CommandDispatcher(
            handlers: [
                new RecordingCommandHandler('PING'),
                new RecordingCommandHandler('ping'),
            ],
            unknownCommand: $this->unknownCommandHandler(),
        );
    }

    private function message(string $command): Message
    {
        return new Message(
            tags: [],
            source: null,
            command: $command,
            parameters: [],
        );
    }

    private function unknownCommandHandler(): UnknownCommandHandler
    {
        return new UnknownCommandHandler(new ServerName('irc.test'));
    }
}
