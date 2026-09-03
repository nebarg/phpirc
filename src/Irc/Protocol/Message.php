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

    public function parameter(int $position): string
    {
        return $this->parameters[$position] ?? '';
    }

    public function optionalParameter(int $position): ?string
    {
        return $this->parameters[$position] ?? null;
    }

    /** Returns true when no parameter was provided at this position. */
    public function isParameterMissing(int $position): bool
    {
        return ! array_key_exists($position, $this->parameters);
    }

    /** Returns true when a provided parameter contains no characters. */
    public function isParameterEmpty(int $position): bool
    {
        return ! $this->isParameterMissing($position) && $this->parameters[$position] === '';
    }

    /**
     * Returns true for typical "blank" behaviour: a missing or empty parameter.
     *
     * The explicit name avoids implying that valid IRC whitespace is blank.
     */
    public function isParameterMissingOrEmpty(int $position): bool
    {
        return $this->isParameterMissing($position) || $this->isParameterEmpty($position);
    }
}
