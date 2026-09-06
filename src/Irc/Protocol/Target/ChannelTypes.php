<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Target;

final readonly class ChannelTypes
{
    public function __construct(
        public string $prefixes = '#',
    ) {}

    /** Whether the target starts with a supported channel type; this does not validate the full name. */
    public function isChannelTarget(string $target): bool
    {
        return $target !== '' && str_contains($this->prefixes, $target[0]);
    }
}
