<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Membership;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
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
        private ModeChangeParser $parser,
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
            $appliedChange = $this->applyChange($context, $channel, $change);

            if ($appliedChange === null) {
                continue;
            }

            $appliedChanges[] = $appliedChange;
        }

        if ($appliedChanges === []) {
            return;
        }

        $this->broadcaster->broadcast(
            $channel,
            new Message(
                command: 'MODE',
                parameters: $this->createModeParameters($channel, $appliedChanges),
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
                parameters: [
                    $channel->name,
                    '+'
                        . implode('', array_map(
                            static fn (ChannelMode $mode): string => $mode->value,
                            $channel->modes(),
                        )),
                ],
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

    private function applyChange(
        CommandContext $context,
        Channel $channel,
        ChannelModeChange|MembershipModeChange $change,
    ): ChannelModeChange|MembershipModeChange|null {
        if ($change instanceof ChannelModeChange) {
            $changed = match ($change->action) {
                ModeAction::Add => $channel->enableMode($change->mode),
                ModeAction::Remove => $channel->disableMode($change->mode),
            };

            return $changed ? $change : null;
        }

        $targetMembership = $this->findTargetMembership($context, $channel, $change->nickname);

        if ($targetMembership === null) {
            return null;
        }

        $changed = match ($change->action) {
            ModeAction::Add => $targetMembership->grant($change->mode),
            ModeAction::Remove => $targetMembership->revoke($change->mode),
        };

        if (! $changed) {
            return null;
        }

        return new MembershipModeChange(
            action: $change->action,
            mode: $change->mode,
            nickname: $targetMembership->client->nickname ?? $change->nickname,
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

    /**
     * @param non-empty-list<ChannelModeChange|MembershipModeChange> $changes
     * @return non-empty-list<string>
     */
    private function createModeParameters(Channel $channel, array $changes): array
    {
        $parameters = [$channel->name, $this->createModeString($changes)];

        foreach ($changes as $change) {
            if (! $change instanceof MembershipModeChange) {
                continue;
            }

            $parameters[] = $change->nickname;
        }

        return $parameters;
    }

    /** @param non-empty-list<ChannelModeChange|MembershipModeChange> $changes */
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
