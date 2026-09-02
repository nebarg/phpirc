<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\ClientDeparture;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;

final readonly class QuitHandler implements PreRegistrationCommandHandler
{
    public function __construct(
        private ClientDeparture $departure,
        private ServerName $serverName,
    ) {}

    public function command(): string
    {
        return 'QUIT';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        $reason = 'Quit: ' . ($message->optionalParameter(0) ?? '');

        $context->connection->send(new Message(
            command: 'ERROR',
            parameters: ["Closing Link: {$context->responseTarget()} ({$reason})"],
            source: $this->serverName->value,
        ));

        $this->departure->depart($context->client, $reason);
        $context->connection->close();
    }
}
