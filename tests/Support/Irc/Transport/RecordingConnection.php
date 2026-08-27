<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\Connection;

final class RecordingConnection implements Connection
{
    /** @var list<Message> */
    public array $messages = [];

    public int $closeCalls = 0;

    public function send(Message $message): void
    {
        $this->messages[] = $message;
    }

    public function close(): void
    {
        $this->closeCalls++;
    }
}
