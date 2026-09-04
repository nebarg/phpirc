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

    /** @var list<string> */
    public array $closeReasons = [];

    /** @var list<string> */
    public array $pongTokens = [];

    public function send(Message $message): void
    {
        $this->messages[] = $message;
    }

    public function close(string $reason = 'Connection closed'): void
    {
        $this->closeCalls++;
        $this->closeReasons[] = $reason;
    }

    public function pongReceived(string $token): void
    {
        $this->pongTokens[] = $token;
    }
}
