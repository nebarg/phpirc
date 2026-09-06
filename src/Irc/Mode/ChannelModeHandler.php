<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipModeChangeParser;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelModeHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private ClientRegistry $clients,
        private ChannelBroadcaster $broadcaster,
        private MembershipModeChangeParser $parser,
        private NumericResponseFactory $responses,
    ) {}

    public function handle(CommandContext $context, Message $message): void
    {
        $channel = $this->channels->find($message->parameter(0));

        if ($channel === null) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoSuchChannel,
                    target: $context->responseTarget(),
                    parameters: [$message->parameter(0)],
                ),
            );

            return;
        }

        if ($message->isParameterMissingOrEmpty(1)) {
            $this->sendCurrentModes($context, $channel);
            return;
        }

        $requesterMembership = $channel->membershipFor($context->client);

        if ($requesterMembership === null) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NotOnChannel,
                    target: $context->responseTarget(),
                    parameters: [$channel->name],
                ),
            );

            return;
        }

        if (! $requesterMembership->has(MembershipMode::Operator)) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::ChannelOperatorPrivilegesNeeded,
                    target: $context->responseTarget(),
                    parameters: [$channel->name],
                ),
            );

            return;
        }

        $result = $this->parser->parse(
            modeString: $message->parameter(1),
            arguments: array_slice($message->parameters, 2),
        );

        foreach ($result->unknownModes as $unknownMode) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::UnknownMode,
                    target: $context->responseTarget(),
                    parameters: [$unknownMode],
                ),
            );
        }

        $appliedChanges = [];

        foreach ($result->changes as $change) {
            $targetMembership = $this->findTargetMembership($context, $channel, $change->nickname);

            if ($targetMembership === null) {
                continue;
            }

            $changed = match ($change->action) {
                ModeAction::Add => $targetMembership->grant($change->mode),
                ModeAction::Remove => $targetMembership->revoke($change->mode),
            };

            if (! $changed) {
                continue;
            }

            $appliedChanges[] = new MembershipModeChange(
                action: $change->action,
                mode: $change->mode,
                nickname: $targetMembership->client->nickname ?? $change->nickname,
            );
        }

        if ($appliedChanges === []) {
            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: 'MODE',
                parameters: [
                    $channel->name,
                    $this->createModeString($appliedChanges),
                    ...array_map(
                        static fn (MembershipModeChange $change): string => $change->nickname,
                        $appliedChanges,
                    ),
                ],
                source: $context->client->nickname,
            ),
        );
    }

    private function sendCurrentModes(CommandContext $context, Channel $channel): void
    {
        $context->connection->send(
            $this->responses->create(
                code: ResponseCode::ChannelModeIs,
                target: $context->responseTarget(),
                parameters: [$channel->name, '+'],
            ),
        );

        $context->connection->send(
            $this->responses->create(
                code: ResponseCode::ChannelCreationTime,
                target: $context->responseTarget(),
                parameters: [$channel->name, (string) $channel->createdAt->getTimestamp()],
            ),
        );
    }

    private function findTargetMembership(
        CommandContext $context,
        Channel $channel,
        string $nickname,
    ): ?Membership {
        $client = $this->clients->findByNickname($nickname);

        if ($client === null || ! $client->registration->isComplete()) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::NoSuchNick,
                    target: $context->responseTarget(),
                    parameters: [$nickname],
                ),
            );

            return null;
        }

        $membership = $channel->membershipFor($client);

        if ($membership === null) {
            $context->connection->send(
                $this->responses->create(
                    code: ResponseCode::UserNotInChannel,
                    target: $context->responseTarget(),
                    parameters: [$client->nickname ?? $nickname, $channel->name],
                ),
            );
        }

        return $membership;
    }

    /** @param non-empty-list<MembershipModeChange> $changes */
    private function createModeString(array $changes): string
    {
        $modeString = '';
        $currentAction = null;

        foreach ($changes as $change) {
            if ($change->action !== $currentAction) {
                $modeString .= $change->action->value;
                $currentAction = $change->action;
            }

            $modeString .= $change->mode->value;
        }

        return $modeString;
    }
}
