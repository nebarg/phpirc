<?php

namespace PhpIrc\Irc\Transport;

interface ClientSocket
{
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
