<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class MessageTag
{
    public ?string $value;

    public function __construct(
        public string $name,
        ?string $value,
    ) {
        $this->value = $value === '' ? null : $value;
    }
}
