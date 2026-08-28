<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class Message
{
    /**
     * @param list<MessageTag> $tags
     * @param list<string> $parameters
     */
    public function __construct(
        public string $command,
        public array $parameters = [],
        public ?string $source = null,
        public array $tags = [],
    ) {}
}
