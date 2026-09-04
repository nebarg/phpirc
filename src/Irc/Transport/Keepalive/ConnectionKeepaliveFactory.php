<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Keepalive;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Timer\TimerScheduler;

final readonly class ConnectionKeepaliveFactory
{
    public function __construct(
        private TimerScheduler $timers,
        private ServerConfig $config,
    ) {}

    public function create(): ConnectionKeepalive
    {
        return new ConnectionKeepalive(
            timers: $this->timers,
            config: $this->config->keepalive,
            serverName: $this->config->serverName,
        );
    }
}
