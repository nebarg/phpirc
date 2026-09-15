<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

interface ClientConnectionSupervisor
{
    public function run(ClientSocket $socket): void;

    public function stopAll(string $reason, bool $notifyClients): void;
}
