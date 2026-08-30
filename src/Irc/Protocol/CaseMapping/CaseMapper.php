<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\CaseMapping;

interface CaseMapper
{
    public function name(): string;

    public function normalise(string $value): string;
}
