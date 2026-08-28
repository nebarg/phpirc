<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Transport\Connection;

final readonly class CommandContext
{
    public function __construct(
        public Connection $connection,
        public Client $client,
    ) {}
}
