<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

final readonly class ChannelNameValidator
{
    public const int MAX_LENGTH = 64;

    public function isValid(string $name): bool
    {
        return strlen($name) <= self::MAX_LENGTH && preg_match('~\A#[^ ,\x07]+\z~', $name) === 1;
    }
}
