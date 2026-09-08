<?php

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\Message;

interface Connection
{
    /**
     * Queues a message for delivery without waiting for the socket write.
     *
     * Transport failures are handled by the connection and are not raised to the caller.
     */
    public function send(Message $message): void;

    /**
     * Queues messages for delivery without waiting for their socket writes.
     *
     * @param iterable<Message> $messages
     */
    public function sendMany(iterable $messages): void;

    public function close(string $reason = 'Connection closed'): void;

    public function pongReceived(string $token): void;
}
