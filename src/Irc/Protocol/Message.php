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
        public array $tags,
        public ?string $source,
        public string $command,
        public array $parameters,
    ) {}
}
