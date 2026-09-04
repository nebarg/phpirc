<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use DateTimeImmutable;

final readonly class ServerConfig
{
    public DateTimeImmutable $startedAt;

    /**
     * @param array<ListenerConfig> $listeners
     */
    public function __construct(
        public ServerName $serverName,
        public string $networkName,
        public array $listeners,
        public string $softwareVersion = 'phpirc-0.1.0',
        public KeepaliveConfig $keepalive = new KeepaliveConfig(),
        ?DateTimeImmutable $startedAt = null,
    ) {
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
    }
}
