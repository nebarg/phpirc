<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class MessageTag
{
    public function __construct(
        public string $name,
        public ?string $value,
    ) {}
}
