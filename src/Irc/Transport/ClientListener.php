<?php

namespace PhpIrc\Irc\Transport;

interface ClientListener
{
    public function accept(): ?ClientSocket;

    public function close(): void;
}
