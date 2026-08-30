<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Client\Client;

final readonly class Membership
{
    public function __construct(
        public Client $client,
        public bool $isOperator,
    ) {}
}
