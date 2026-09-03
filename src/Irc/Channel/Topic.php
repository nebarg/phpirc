<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use DateTimeImmutable;

final readonly class Topic
{
    public function __construct(
        public string $text,
        public string $setBy,
        public DateTimeImmutable $setAt,
    ) {}
}
