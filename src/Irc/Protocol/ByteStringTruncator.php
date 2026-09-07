<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

use InvalidArgumentException;

final readonly class ByteStringTruncator
{
    public function truncate(string $value, int $maximumBytes): string
    {
        if ($maximumBytes < 0) {
            throw new InvalidArgumentException('Maximum bytes cannot be negative.');
        }

        if (strlen($value) <= $maximumBytes) {
            return $value;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return substr($value, 0, $maximumBytes);
        }

        return mb_strcut($value, 0, $maximumBytes, 'UTF-8');
    }
}
