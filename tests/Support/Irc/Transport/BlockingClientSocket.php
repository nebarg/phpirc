<?php

declare(strict_types=1);

namespace Tests\Support\Irc\Transport;

use PhpIrc\Irc\Transport\ClientSocket;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class BlockingClientSocket implements ClientSocket
{
    private ?Suspension $readSuspension = null;

    private bool $reading = false;

    public int $closeCalls = 0;

    /** @var list<string> */
    public array $writes = [];

    public function __construct(
        private readonly string $remoteAddress = '127.0.0.1',
    ) {}

    public function remoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function read(): ?string
    {
        $this->reading = true;
        $this->readSuspension = EventLoop::getSuspension();
        $this->readSuspension->suspend();

        return null;
    }

    public function write(string $bytes): void
    {
        $this->writes[] = $bytes;
    }

    public function close(): void
    {
        $this->closeCalls++;

        if (! $this->reading) {
            return;
        }

        $this->reading = false;
        $this->readSuspension?->resume();
        $this->readSuspension = null;
    }
}
