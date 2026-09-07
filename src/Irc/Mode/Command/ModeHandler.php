<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Mode\ChannelModeHandler;
use PhpIrc\Irc\Mode\UserModeHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PhpIrc\Irc\Protocol\Target\TargetType;

final readonly class ModeHandler implements CommandHandler
{
    public function __construct(
        private ChannelModeHandler $channelModes,
        private UserModeHandler $userModes,
        private NumericErrorResponseFactory $errors,
        private TargetClassifier $targets,
    ) {}

    public function command(): string
    {
        return 'MODE';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        if ($this->targets->classify($message->parameter(0)) === TargetType::Channel) {
            $this->channelModes->handle($context, $message);
            return;
        }

        $this->userModes->handle($context, $message);
    }
}
