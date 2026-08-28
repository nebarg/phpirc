<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

final class RecordingMessageHandler implements MessageHandler
{
    /** @var list<CommandContext> */
    public array $contexts = [];

    /** @var list<Connection> */
    public array $connections = [];

    /** @var list<Message> */
    public array $messages = [];

    public function handle(CommandContext $context, Message $message): void
    {
        $this->contexts[] = $context;
        $this->connections[] = $context->connection;
        $this->messages[] = $message;
    }
}
