<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Response;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Mode\UserModeChange;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class UserModeResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
        private NumericErrorResponseFactory $errors,
    ) {}

    public function createUnknownNicknameResponse(string $target, string $nickname): Message
    {
        return $this->errors->noSuchNickname($target, $nickname);
    }

    public function createOtherUserResponse(string $target): Message
    {
        return $this->errors->usersDontMatch($target);
    }

    public function createCurrentModesResponse(string $target, Client $client): Message
    {
        $modes = array_map(
            static fn (UserMode $mode): string => $mode->value,
            $client->modes(),
        );

        return $this->responses->create(
            code: ResponseCode::UserModeIs,
            target: $target,
            parameters: ['+' . implode('', $modes)],
        );
    }

    public function createUnknownModeResponse(string $target): Message
    {
        return $this->errors->unknownUserModeFlag($target);
    }

    /** @param non-empty-list<UserModeChange> $changes */
    public function createChangedMessage(string $source, string $nickname, array $changes): Message
    {
        return new Message(
            command: 'MODE',
            parameters: [$nickname, $this->createModeString($changes)],
            source: $source,
        );
    }

    /** @param non-empty-list<UserModeChange> $changes */
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
