<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\CaseMapping;

use Tempest\Container\Autowire;
use Tempest\Container\Singleton;

#[Autowire]
#[Singleton]
final class AsciiCaseMapper implements CaseMapper
{
    public function name(): string
    {
        return 'ascii';
    }

    public function normalise(string $value): string
    {
        return strtolower($value);
    }
}
