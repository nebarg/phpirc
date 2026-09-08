<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use InvalidArgumentException;
use PhpIrc\Irc\Config\ServerLimits;

final readonly class Motd
{
    /** @param list<string> $lines */
    public function __construct(
        public array $lines = [],
    ) {
        foreach ($lines as $line) {
            if (preg_match('/[\x00\r\n]/', $line) !== 0) {
                throw new InvalidArgumentException('MOTD lines cannot contain NUL, carriage-return or line-feed characters.');
            }

            if (strlen($line) > ServerLimits::MAX_MOTD_LINE_BYTES) {
                throw new InvalidArgumentException(
                    sprintf('MOTD lines cannot exceed %d bytes.', ServerLimits::MAX_MOTD_LINE_BYTES),
                );
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
