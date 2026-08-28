<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use LogicException;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingCommandHandler;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class CommandDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_to_the_handler_for_the_message_command(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $unknownCommand = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = $this->message('ping');

        $dispatcher->handle($context, $message);

        $this->assertSame([$context], $handler->contexts);
        $this->assertSame([$connection], $handler->connections);
        $this->assertSame([$message], $handler->messages);
        $this->assertSame([], $unknownCommand->messages);
    }

    #[Test]
    public function it_uses_the_unknown_handler_when_no_command_matches(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $unknownCommand = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = $this->message('WHATEVER');

        $dispatcher->handle($context, $message);

        $this->assertSame([], $handler->messages);
        $this->assertSame([$context], $unknownCommand->contexts);
        $this->assertSame([$message], $unknownCommand->messages);
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
            unknownCommand: new RecordingMessageHandler(),
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
}
