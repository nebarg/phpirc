<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Network;

final readonly class NicknameValidator
{
    public const int MAX_LENGTH = 30;

    private const string PATTERN = '~\A[A-Za-z\[\]{}|_`^\x5C][A-Za-z0-9\[\]{}|_`^\x5C-]{0,29}\z~';

    public function isValid(string $nickname): bool
    {
        return preg_match(self::PATTERN, $nickname) === 1;
    }
}
