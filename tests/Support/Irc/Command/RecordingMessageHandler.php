<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Command;

use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

final class RecordingMessageHandler implements MessageHandler
{
    /** @var list<Connection> */
    public array $connections = [];

    /** @var list<Message> */
    public array $messages = [];

    public function handle(Connection $connection, Message $message): void
    {
        $this->connections[] = $connection;
        $this->messages[] = $message;
    }
}
