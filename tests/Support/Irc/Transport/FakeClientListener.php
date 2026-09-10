<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use Closure;
use PhpIrc\Irc\Transport\ClientListener;
use PhpIrc\Irc\Transport\ClientSocket;

final class FakeClientListener implements ClientListener
{
    public int $acceptCalls = 0;

    public int $closeCalls = 0;

    private int $nextSocket = 0;

    private bool $closed = false;

    /**
     * @param list<ClientSocket> $sockets
     * @param null|Closure(int): void $beforeAccept
     */
    public function __construct(
        private readonly array $sockets = [],
        private readonly ?Closure $beforeAccept = null,
    ) {}

    public function accept(): ?ClientSocket
    {
        $this->acceptCalls++;

        if ($this->beforeAccept !== null) {
            ($this->beforeAccept)($this->acceptCalls);
        }

        if ($this->closed) {
            return null;
        }

        return $this->sockets[$this->nextSocket++] ?? null;
    }

    public function close(): void
    {
        $this->closeCalls++;
        $this->closed = true;
    }
}
