<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\ResponseCode;

final readonly class UserHandler implements PreRegistrationCommandHandler
{
    private const int MIN_PARAMETERS_ALLOWED = 4;

    public function __construct(
        private NumericResponseFactory $responses,
        private RegistrationCompleter $registration,
    ) {}

    public function command(): string
    {
        return 'USER';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($context->client->registration->isComplete() || $context->client->username !== null) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::AlreadyRegistered,
                    target: $context->client->nickname,
                ),
            );

            return;
        }

        if (count($message->parameters) < self::MIN_PARAMETERS_ALLOWED || $message->parameters[0] === '') {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NeedMoreParameters,
                    target: $context->client->nickname,
                    parameters: [$this->command()],
                ),
            );

            return;
        }

        $context->client->setUsername($message->parameters[0]);
        $context->client->setRealName($message->parameters[3]);

        $this->registration->completeIfReady($context);
    }
}
