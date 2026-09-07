<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\PreRegistrationCommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class UserHandler implements PreRegistrationCommandHandler
{
    private const int MIN_PARAMETERS_ALLOWED = 4;

    public function __construct(
        private NumericErrorResponseFactory $errors,
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
                $this->errors->alreadyRegistered($context->responseTarget()),
            );

            return;
        }

        if (count($message->parameters) < self::MIN_PARAMETERS_ALLOWED || $message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $context->client->setUsername($message->parameter(0));
        $context->client->setRealName($message->parameter(3));

        $this->registration->completeIfReady($context);
    }
}
