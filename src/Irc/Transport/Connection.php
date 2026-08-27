<?php

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\Message;

interface Connection
{
    public function send(Message $message): void;

    public function close(): void;
}
