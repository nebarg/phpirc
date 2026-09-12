<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Capability;

final class ClientCapabilities
{
    /** @var array<string, Capability> */
    private array $enabled = [];

    public function enable(Capability $capability): bool
    {
        if ($this->has($capability)) {
            return false;
        }

        $this->enabled[$capability->value] = $capability;

        return true;
    }

    public function disable(Capability $capability): bool
    {
        if (! $this->has($capability)) {
            return false;
        }

        unset($this->enabled[$capability->value]);

        return true;
    }

    public function has(Capability $capability): bool
    {
        return isset($this->enabled[$capability->value]);
    }

    /** @return list<Capability> */
    public function all(): array
    {
        return array_values($this->enabled);
    }
}
