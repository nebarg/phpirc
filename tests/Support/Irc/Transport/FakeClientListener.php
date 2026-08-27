<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientSocket;

final class FakeClientListener implements ClientListener
{
    public int $acceptCalls = 0;

    public int $closeCalls = 0;

    private int $nextSocket = 0;

    /** @param list<ClientSocket> $sockets */
    public function __construct(
        private readonly array $sockets = [],
    ) {}

    public function accept(): ?ClientSocket
    {
        $this->acceptCalls++;

        return $this->sockets[$this->nextSocket++] ?? null;
    }

    public function close(): void
    {
        $this->closeCalls++;
    }
}
