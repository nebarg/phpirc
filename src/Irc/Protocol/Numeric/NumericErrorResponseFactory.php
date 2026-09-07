<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Numeric;

use PhpIrc\Irc\Protocol\Message;

final readonly class NumericErrorResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
    ) {}

    public function needMoreParameters(string $target, string $command): Message
    {
        return $this->responses->create(ResponseCode::NeedMoreParameters, $target, [$command]);
    }

    public function noSuchChannel(string $target, string $channel): Message
    {
        return $this->responses->create(ResponseCode::NoSuchChannel, $target, [$this->nameOrWildcard($channel)]);
    }

    public function notOnChannel(string $target, string $channel): Message
    {
        return $this->responses->create(ResponseCode::NotOnChannel, $target, [$channel]);
    }

    public function noSuchNickname(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::NoSuchNick, $target, [$this->nameOrWildcard($nickname)]);
    }

    public function userNotInChannel(string $target, string $nickname, string $channel): Message
    {
        return $this->responses->create(ResponseCode::UserNotInChannel, $target, [$nickname, $channel]);
    }

    public function channelOperatorPrivilegesNeeded(string $target, string $channel): Message
    {
        return $this->responses->create(ResponseCode::ChannelOperatorPrivilegesNeeded, $target, [$channel]);
    }

    public function noNicknameGiven(string $target): Message
    {
        return $this->responses->create(ResponseCode::NoNicknameGiven, $target);
    }

    public function erroneousNickname(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::ErroneousNickname, $target, [$nickname]);
    }

    public function nicknameInUse(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::NicknameInUse, $target, [$nickname]);
    }

    public function noOrigin(string $target): Message
    {
        return $this->responses->create(ResponseCode::NoOrigin, $target);
    }

    public function alreadyRegistered(string $target): Message
    {
        return $this->responses->create(ResponseCode::AlreadyRegistered, $target);
    }

    public function invalidCapabilityCommand(string $target, string $subcommand): Message
    {
        return $this->responses->create(
            ResponseCode::InvalidCapCommand,
            $target,
            [$this->nameOrWildcard($subcommand)],
        );
    }

    public function noRecipient(string $target): Message
    {
        return $this->responses->create(ResponseCode::NoRecipient, $target);
    }

    public function noTextToSend(string $target): Message
    {
        return $this->responses->create(ResponseCode::NoTextToSend, $target);
    }

    public function cannotSendToChannel(string $target, string $channel): Message
    {
        return $this->responses->create(
            ResponseCode::CannotSendToChannel,
            $target,
            [$this->nameOrWildcard($channel)],
        );
    }

    public function unknownMode(string $target, string $mode): Message
    {
        return $this->responses->create(ResponseCode::UnknownMode, $target, [$mode]);
    }

    public function usersDontMatch(string $target): Message
    {
        return $this->responses->create(ResponseCode::UsersDontMatch, $target);
    }

    public function unknownUserModeFlag(string $target): Message
    {
        return $this->responses->create(ResponseCode::UnknownUserModeFlag, $target);
    }

    public function notRegistered(string $target): Message
    {
        return $this->responses->create(ResponseCode::NotRegistered, $target);
    }

    public function unknownCommand(string $target, string $command): Message
    {
        return $this->responses->create(ResponseCode::UnknownCommand, $target, [$command]);
    }

    private function nameOrWildcard(string $name): string
    {
        return $name === '' ? '*' : $name;
    }
}
