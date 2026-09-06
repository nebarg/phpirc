<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;

final class Membership
{
    /** @var array<string, MembershipMode> */
    private array $modes = [];

    public function __construct(
        public readonly Client $client,
    ) {}

    public function grant(MembershipMode $mode): bool
    {
        if ($this->has($mode)) {
            return false;
        }

        $this->modes[$mode->value] = $mode;

        return true;
    }

    public function revoke(MembershipMode $mode): bool
    {
        if (! $this->has($mode)) {
            return false;
        }

        unset($this->modes[$mode->value]);

        return true;
    }

    public function has(MembershipMode $mode): bool
    {
        return isset($this->modes[$mode->value]);
    }

    public function highestPrefix(): string
    {
        $highest = null;

        foreach ($this->modes as $mode) {
            if ($highest !== null && $mode->prefixRank() <= $highest->prefixRank()) {
                continue;
            }

            $highest = $mode;
        }

        return $highest?->prefix() ?? '';
    }
}
