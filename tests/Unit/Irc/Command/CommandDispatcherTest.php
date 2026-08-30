<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandDispatcher;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingCommandHandler;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Command\RecordingPreRegistrationCommandHandler;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class CommandDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_to_the_handler_for_the_message_command(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $unknownCommand = new RecordingMessageHandler();
        $notRegistered = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
            notRegistered: $notRegistered,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, $this->registeredClient());
        $message = $this->message('ping');

        $dispatcher->handle($context, $message);

        $this->assertSame([$context], $handler->contexts);
        $this->assertSame([$connection], $handler->connections);
        $this->assertSame([$message], $handler->messages);
        $this->assertSame([], $unknownCommand->messages);
        $this->assertSame([], $notRegistered->messages);
    }

    #[Test]
    public function it_uses_the_unknown_handler_when_no_command_matches(): void
    {
        $handler = new RecordingCommandHandler('PING');
        $unknownCommand = new RecordingMessageHandler();
        $notRegistered = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
            notRegistered: $notRegistered,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = $this->message('WHATEVER');

        $dispatcher->handle($context, $message);

        $this->assertSame([], $handler->messages);
        $this->assertSame([$context], $unknownCommand->contexts);
        $this->assertSame([$message], $unknownCommand->messages);
        $this->assertSame([], $notRegistered->messages);
    }

    #[Test]
    public function it_uses_the_not_registered_handler_for_a_restricted_command(): void
    {
        $handler = new RecordingCommandHandler('JOIN');
        $unknownCommand = new RecordingMessageHandler();
        $notRegistered = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
            notRegistered: $notRegistered,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = $this->message('JOIN');

        $dispatcher->handle($context, $message);

        $this->assertSame([], $handler->messages);
        $this->assertSame([], $unknownCommand->messages);
        $this->assertSame([$context], $notRegistered->contexts);
        $this->assertSame([$connection], $notRegistered->connections);
        $this->assertSame([$message], $notRegistered->messages);
    }

    #[Test]
    public function it_dispatches_a_pre_registration_command_for_an_unregistered_client(): void
    {
        $handler = new RecordingPreRegistrationCommandHandler('PING');
        $unknownCommand = new RecordingMessageHandler();
        $notRegistered = new RecordingMessageHandler();
        $dispatcher = new CommandDispatcher(
            handlers: [$handler],
            unknownCommand: $unknownCommand,
            notRegistered: $notRegistered,
        );
        $connection = new RecordingConnection();
        $context = new CommandContext($connection, new Client());
        $message = $this->message('PING');

        $dispatcher->handle($context, $message);

        $this->assertSame([$context], $handler->contexts);
        $this->assertSame([$connection], $handler->connections);
        $this->assertSame([$message], $handler->messages);
        $this->assertSame([], $unknownCommand->messages);
        $this->assertSame([], $notRegistered->messages);
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
            notRegistered: new RecordingMessageHandler(),
        );
    }

    private function registeredClient(): Client
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');
        $client->completeRegistrationIfReady();

        return $client;
    }

    private function message(string $command): Message
    {
        return new Message(
            command: $command,
        );
    }
}
