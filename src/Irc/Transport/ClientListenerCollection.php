<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use InvalidArgumentException;

final readonly class ClientListenerCollection
{
    /** @var non-empty-list<ClientListener> */
    private array $listeners;

    /** @param list<ClientListener> $listeners */
    public function __construct(array $listeners)
    {
        if ($listeners === []) {
            throw new InvalidArgumentException('At least one client listener is required.');
        }

        $this->listeners = array_values($listeners);
    }

    /** @return non-empty-list<ClientListener> */
    public function all(): array
    {
        return $this->listeners;
    }
}
