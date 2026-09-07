<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Numeric;

use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\Message;

final readonly class NumericErrorResponseFactory
{
    public function __construct(
        private NumericResponseFactory $responses,
        private ByteStringTruncator $strings,
    ) {}

    public function needMoreParameters(string $target, string $command): Message
    {
        return $this->responses->create(ResponseCode::NeedMoreParameters, $target, [$this->command($command)]);
    }

    public function noSuchChannel(string $target, string $channel): Message
    {
        return $this->responses->create(ResponseCode::NoSuchChannel, $target, [$this->channel($channel)]);
    }

    public function notOnChannel(string $target, string $channel): Message
    {
        return $this->responses->create(ResponseCode::NotOnChannel, $target, [$this->channel($channel)]);
    }

    public function noSuchNickname(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::NoSuchNick, $target, [$this->nickname($nickname)]);
    }

    public function userNotInChannel(string $target, string $nickname, string $channel): Message
    {
        return $this->responses->create(
            ResponseCode::UserNotInChannel,
            $target,
            [$this->nickname($nickname), $this->channel($channel)],
        );
    }

    public function channelOperatorPrivilegesNeeded(string $target, string $channel): Message
    {
        return $this->responses->create(
            ResponseCode::ChannelOperatorPrivilegesNeeded,
            $target,
            [$this->channel($channel)],
        );
    }

    public function noNicknameGiven(string $target): Message
    {
        return $this->responses->create(ResponseCode::NoNicknameGiven, $target);
    }

    public function erroneousNickname(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::ErroneousNickname, $target, [$this->nickname($nickname)]);
    }

    public function nicknameInUse(string $target, string $nickname): Message
    {
        return $this->responses->create(ResponseCode::NicknameInUse, $target, [$this->nickname($nickname)]);
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
            [$this->command($subcommand)],
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
            [$this->channel($channel)],
        );
    }

    public function unknownMode(string $target, string $mode): Message
    {
        return $this->responses->create(ResponseCode::UnknownMode, $target, [$this->safeParameter($mode, 1)]);
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
        return $this->responses->create(ResponseCode::UnknownCommand, $target, [$this->command($command)]);
    }

    private function nickname(string $nickname): string
    {
        return $this->safeParameter($nickname, ServerLimits::MAX_NICKNAME_BYTES);
    }

    private function channel(string $channel): string
    {
        return $this->safeParameter($channel, ServerLimits::MAX_CHANNEL_NAME_BYTES);
    }

    private function command(string $command): string
    {
        return $this->safeParameter($command, ServerLimits::MAX_COMMAND_BYTES);
    }

    private function safeParameter(string $parameter, int $maximumBytes): string
    {
        if ($parameter === '') {
            return '*';
        }

        $parameter = strtr($parameter, [
            "\0" => '?',
            "\r" => '?',
            "\n" => '?',
            ' ' => '?',
        ]);

        if ($parameter[0] === ':') {
            $parameter[0] = '?';
        }

        $parameter = $this->strings->truncate($parameter, $maximumBytes);

        return $parameter === '' ? '*' : $parameter;
    }
}
