<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class ListenerConfig
{
    public function __construct(
        private string $address,
        private int $port,
    ) {
        if ($address === '') {
            throw new InvalidArgumentException(
                'Listener address cannot be empty.',
            );
        }

        if ($port < 1 || $port > 65_535) {
            throw new InvalidArgumentException(
                'Listener port must be between 1 and 65535.',
            );
        }
    }

    public function address(): string
    {
        return "{$this->address}:{$this->port}";
    }
}
