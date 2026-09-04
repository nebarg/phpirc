<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Flood;

use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Transport\Time\MonotonicClock;

final class MessageRateLimiter
{
    private float $availableMessages;

    private float $lastRefillAt;

    public function __construct(
        private readonly MonotonicClock $clock,
        private readonly FloodProtectionConfig $config,
    ) {
        $this->availableMessages = $config->burstMessages;
        $this->lastRefillAt = $clock->now();
    }

    public function accept(): bool
    {
        $this->refill();

        if ($this->availableMessages < 1) {
            return false;
        }

        --$this->availableMessages;

        return true;
    }

    private function refill(): void
    {
        $now = $this->clock->now();
        $elapsed = $now - $this->lastRefillAt;
        $this->lastRefillAt = $now;

        $this->availableMessages = min(
            $this->config->burstMessages,
            $this->availableMessages + ($elapsed * $this->config->messagesPerSecond),
        );
    }
}
