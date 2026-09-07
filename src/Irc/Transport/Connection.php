<?php

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\Message;

interface Connection
{
    public function send(Message $message): void;

    /** @param iterable<Message> $messages */
    public function sendMany(iterable $messages): void;

    public function close(string $reason = 'Connection closed'): void;

    public function pongReceived(string $token): void;
}
