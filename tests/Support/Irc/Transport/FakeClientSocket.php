<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use PhpIrc\Irc\Transport\ClientSocket;

final class FakeClientSocket implements ClientSocket
{
    /** @var list<string> */
    private array $chunks;

    private int $nextChunk = 0;

    public int $readCalls = 0;

    /** @var list<string> */
    public array $writes = [];

    public int $closeCalls = 0;

    /** @param list<string> $chunks */
    public function __construct(array $chunks = [])
    {
        $this->chunks = $chunks;
    }

    public function read(): ?string
    {
        $this->readCalls++;

        return $this->chunks[$this->nextChunk++] ?? null;
    }

    public function write(string $bytes): void
    {
        $this->writes[] = $bytes;
    }

    public function close(): void
    {
        $this->closeCalls++;
    }
}
