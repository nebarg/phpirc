<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

final readonly class ServerConfig
{
    /**
     * @param array<ListenerConfig> $listeners
     */
    public function __construct(
        public ServerName $serverName,
        public string $networkName,
        public array $listeners,
    ) {}
}
