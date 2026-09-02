<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Transport\Connection;

final readonly class ConnectedClient
{
    public function __construct(
        public Client $client,
        public Connection $connection,
    ) {}
}
