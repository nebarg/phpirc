<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class WebsocketConfig
{
    /** @var non-empty-list<non-empty-string> */
    public array $allowedOrigins;

    /** @var list<non-empty-string> */
    public array $trustedProxies;

    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private string $address,
        private int $port,
        public string $path = '/irc',
        array $allowedOrigins = [],
        array $trustedProxies = [],
    ) {
        if ($address === '') {
            throw new InvalidArgumentException('WebSocket address cannot be empty.');
        }

        if ($port < 1 || $port > 65_535) {
            throw new InvalidArgumentException('WebSocket port must be between 1 and 65535.');
        }

        if (! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidArgumentException(
                'WebSocket path must begin with / and cannot contain a query or fragment.',
            );
        }

        if ($allowedOrigins === []) {
            throw new InvalidArgumentException('At least one WebSocket origin must be allowed.');
        }

        if (in_array('', $allowedOrigins, true)) {
            throw new InvalidArgumentException('Allowed WebSocket origins cannot be empty.');
        }

        if (in_array('', $trustedProxies, true)) {
            throw new InvalidArgumentException('Trusted WebSocket proxies cannot be empty.');
        }

        /** @var non-empty-list<non-empty-string> $allowedOrigins */
        $this->allowedOrigins = $allowedOrigins;
        /** @var list<non-empty-string> $trustedProxies */
        $this->trustedProxies = $trustedProxies;
    }

    public function address(): string
    {
        return "{$this->address}:{$this->port}";
    }
}
