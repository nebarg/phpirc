<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Config\HostCloakConfig;

/**
 * Stands in for the address a client connected from so commands can still
 * tell people apart without publishing where they are.
 *
 * The digest is keyed. An unkeyed digest would name the address it came from; the secret is
 * what makes that infeasible. It is also stable, which is what lets a mask keep
 * working for bans and for recognising someone across a nickname change.
 */
final readonly class HostCloak
{
    public function __construct(
        private HostCloakConfig $config,
    ) {}

    public function mask(string $hostname): string
    {
        if (! $this->config->enabled || $hostname === '') {
            return $hostname;
        }

        $digest = substr(
            hash_hmac('sha256', $hostname, $this->config->secret),
            0,
            HostCloakConfig::DIGEST_LENGTH,
        );

        return "{$digest}.{$this->config->suffix}";
    }
}
