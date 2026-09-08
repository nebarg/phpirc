<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class EncodedMessage
{
    public function __construct(
        public string $bytes,
        public int $mainSectionBytes,
    ) {}
}
