<?php

namespace PhpIrc\Irc\Transport;

interface ClientSocket
{
    public function remoteAddress(): string;

    /**
     * @throws ClientSocketException
     */
    public function read(): ?string;

    /**
     * @throws ClientSocketException
     */
    public function write(string $bytes): void;

    public function close(): void;
}
