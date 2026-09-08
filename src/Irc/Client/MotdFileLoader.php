<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use RuntimeException;

final readonly class MotdFileLoader
{
    public function load(?string $path): Motd
    {
        if ($path === null || ! is_file($path)) {
            return new Motd();
        }

        if (! is_readable($path)) {
            throw new RuntimeException("MOTD file is not readable: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException("Could not read MOTD file: {$path}");
        }

        return new Motd(array_values($lines));
    }
}
