<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Command;

use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

final class RecordingCommandHandler implements CommandHandler
{
    /** @var list<Connection> */
    public array $connections = [];

    /** @var list<Message> */
    public array $messages = [];

    public function __construct(private readonly string $handledCommand) {}

    public function command(): string
    {
        return $this->handledCommand;
    }

    public function handle(Connection $connection, Message $message): void
    {
        $this->connections[] = $connection;
        $this->messages[] = $message;
    }
}
