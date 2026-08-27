<?php

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

interface MessageHandler
{
    public function handle(Connection $connection, Message $message): void;
}
