<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Flood;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientSocketException;

final readonly class RateLimitedMessageHandler implements MessageHandler
{
    public function __construct(
        private MessageHandler $next,
        private MessageRateLimiter $limiter,
        private ServerName $serverName,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        if ($this->limiter->accept()) {
            $this->next->handle($context, $message);

            return;
        }

        try {
            $context->connection->send(new Message(
                command: 'ERROR',
                parameters: ['Excess flood'],
                source: $this->serverName->value,
            ));
        } catch (ClientSocketException) {
            // The socket is already unusable, but the connection still needs closing.
        } finally {
            $context->connection->close('Excess flood');
        }
    }
}
