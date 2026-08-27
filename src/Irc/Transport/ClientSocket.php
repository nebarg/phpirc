<?php

namespace PhpIrc\Irc\Transport;

interface ClientSocket
{
    public function read(): ?string;

    public function write(string $bytes): void;

    public function close(): void;
}
