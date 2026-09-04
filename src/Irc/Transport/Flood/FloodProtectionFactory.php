<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Flood;

use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Time\MonotonicClock;

final readonly class FloodProtectionFactory
{
    public function __construct(
        private MonotonicClock $clock,
        private ServerConfig $config,
    ) {}

    public function protect(MessageHandler $handler): MessageHandler
    {
        return new RateLimitedMessageHandler(
            next: $handler,
            limiter: new MessageRateLimiter(
                clock: $this->clock,
                config: $this->config->floodProtection,
            ),
            serverName: $this->config->serverName,
        );
    }
}
