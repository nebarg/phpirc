<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Response\WhoisResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\CommandHandler;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class WhoisHandler implements CommandHandler
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private WhoisResponseFactory $whoisResponses,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function command(): string
    {
        return 'WHOIS';
    }

    public function handle(CommandContext $context, Message $message): void
    {
        if ($message->isParameterMissingOrEmpty(0)) {
            $context->connection->send(
                $this->errors->noNicknameGiven($context->responseTarget()),
            );

            return;
        }

        $requestedNickname = $message->parameter(0);
        $client = $this->clients->findByNickname($requestedNickname);

        if ($client === null || ! $client->registration->isComplete()) {
            $context->connection->sendMany([
                $this->errors->noSuchNickname($context->responseTarget(), $requestedNickname),
                $this->whoisResponses->createEndOfWhoisResponse(
                    $context->responseTarget(),
                    $requestedNickname,
                ),
            ]);

            return;
        }

        $context->connection->sendMany(
            $this->whoisResponses->createWhoisResponses(
                target: $context->responseTarget(),
                requestedNickname: $requestedNickname,
                client: $client,
                channels: $this->channels->channelsFor($client),
            ),
        );
    }
}
