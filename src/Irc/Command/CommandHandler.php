<?php

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

interface CommandHandler
{
    public function command(): string;

    public function handle(Connection $connection, Message $message): void;
}
