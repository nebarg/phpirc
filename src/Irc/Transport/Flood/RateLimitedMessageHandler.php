<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Flood;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;

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

        $context->connection->send(new Message(
            command: 'ERROR',
            parameters: ['Excess flood'],
            source: $this->serverName->value,
        ));
        $context->connection->close('Excess flood');
    }
}
