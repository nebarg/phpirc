<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class ServerName
{
    public function __construct(
        public string $value,
    ) {
        if ($value === '' || preg_match('/[\x00\r\n :]/', $value) !== 0) {
            throw new InvalidArgumentException('Invalid server name.');
        }
    }
}
