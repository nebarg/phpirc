<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel\Response;

use LogicException;
use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;

final readonly class ChannelPermissionResponseFactory
{
    public function __construct(
        private NumericErrorResponseFactory $errors,
    ) {}

    public function createTopicChangeDeniedResponse(
        ChannelPermission $permission,
        string $target,
        Channel $channel,
    ): Message {
        return match ($permission) {
            ChannelPermission::NotMember => $this->errors->notOnChannel($target, $channel->name),
            ChannelPermission::InsufficientPrivileges => $this->errors->channelOperatorPrivilegesNeeded(
                $target,
                $channel->name,
            ),
            ChannelPermission::Allowed => throw new LogicException('An allowed channel permission has no error response.'),
        };
    }

    public function createModeChangeDeniedResponse(
        ChannelPermission $permission,
        string $target,
        Channel $channel,
    ): Message {
        return match ($permission) {
            ChannelPermission::NotMember, ChannelPermission::InsufficientPrivileges => $this->errors->channelOperatorPrivilegesNeeded(
                $target,
                $channel->name,
            ),
            ChannelPermission::Allowed => throw new LogicException('An allowed channel permission has no error response.'),
        };
    }

    public function createKickDeniedResponse(
        ChannelPermission $permission,
        string $target,
        Channel $channel,
    ): Message {
        return match ($permission) {
            ChannelPermission::NotMember => $this->errors->notOnChannel($target, $channel->name),
            ChannelPermission::InsufficientPrivileges => $this->errors->channelOperatorPrivilegesNeeded(
                $target,
                $channel->name,
            ),
            ChannelPermission::Allowed => throw new LogicException('An allowed channel permission has no error response.'),
        };
    }
}
