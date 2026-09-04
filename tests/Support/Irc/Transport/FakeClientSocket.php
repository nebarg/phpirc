<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use Closure;
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

    /**
     * @param list<string> $chunks
     * @param null|Closure(int): void $beforeRead
     */
    public function __construct(
        array $chunks = [],
        private readonly ?Closure $beforeRead = null,
        private readonly string $remoteAddress = '127.0.0.1',
    ) {
        $this->chunks = $chunks;
    }

    public function remoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function read(): ?string
    {
        $this->readCalls++;

        if ($this->beforeRead !== null) {
            ($this->beforeRead)($this->readCalls);
        }

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
