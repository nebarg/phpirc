<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class ChannelModeResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    /** @return array{Message, Message} */
    public function createCurrentModeResponses(string $target, Channel $channel): array
    {
        $modes = array_map(
            static fn (ChannelMode $mode): string => $mode->value,
            $channel->modes(),
        );
        $modeString = '+' . implode('', $modes);

        return [
            $this->responses->create(
                code: ResponseCode::ChannelModeIs,
                target: $target,
                parameters: [$channel->name, $modeString],
            ),
            $this->responses->create(
                code: ResponseCode::ChannelCreationTime,
                target: $target,
                parameters: [$channel->name, (string) $channel->createdAt->getTimestamp()],
            ),
        ];
    }

    /** @param non-empty-list<ChannelModeChange|MembershipModeChange> $changes */
    public function createChangedMessage(string $source, Channel $channel, array $changes): Message
    {
        return new Message(
            command: 'MODE',
            parameters: $this->createModeParameters($channel, $changes),
            source: $source,
        );
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
