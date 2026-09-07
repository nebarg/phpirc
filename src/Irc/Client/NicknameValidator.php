<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Config\ServerLimits;

final readonly class NicknameValidator
{
    public const int MAX_LENGTH = ServerLimits::MAX_NICKNAME_BYTES;

    private const string PATTERN = '~\A[A-Za-z\[\]{}|_`^\x5C][A-Za-z0-9\[\]{}|_`^\x5C-]*\z~';

    public function isValid(string $nickname): bool
    {
        if (strlen($nickname) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::PATTERN, $nickname) === 1;
    }
}
