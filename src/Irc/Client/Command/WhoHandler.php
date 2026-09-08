<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Response\WhoResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class WhoHandler implements CommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private WhoResponseFactory $whoResponses,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function command(): string
    {
        return 'WHO';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->errors->needMoreParameters($context->responseTarget(), $this->command()),
            );

            return;
        }

        $mask = $message->parameter(0);
        $channel = $this->channels->find($mask);

        if ($channel !== null) {
            foreach ($channel->members() as $membership) {
                $context->connection->send(
                    $this->whoResponses->createChannelMemberReply(
                        target: $context->responseTarget(),
                        channel: $channel,
                        membership: $membership,
                    ),
                );
            }

            $this->sendEndOfWhoResponse($context, $mask);

            return;
        }

        $client = $this->clients->findByNickname($mask);

        if ($client !== null && $client->registration->isComplete()) {
            $context->connection->send(
                $this->whoResponses->createClientReply(
                    target: $context->responseTarget(),
                    client: $client,
                ),
            );
        }

        $this->sendEndOfWhoResponse($context, $mask);
    }

    private function sendEndOfWhoResponse(CommandContext $context, string $mask): void
    {
        $context->connection->send(
            $this->whoResponses->createEndOfWhoResponse(
                target: $context->responseTarget(),
                mask: $mask,
            ),
        );
    }
}
