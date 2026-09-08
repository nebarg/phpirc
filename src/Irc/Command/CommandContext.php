<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Transport\Connection;

final readonly class CommandContext
{
    public function __construct(
        public Connection $connection,
        public Client $client,
    ) {}

    /** Returns the client target used by numeric responses, or `*` before a nickname is set. */
    public function responseTarget(): string
    {
        return $this->client->nickname ?? '*';
    }

    /** Returns the nickname of the registered client performing the command. */
    public function actorNickname(): string
    {
        if ($this->client->nickname === null) {
            throw new LogicException('A registered command actor must have a nickname.');
        }

        return $this->client->nickname;
    }
}
