<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Channel;

use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;

final readonly class ChannelNameValidator
{
    public const int MAX_LENGTH = ServerLimits::MAX_CHANNEL_NAME_BYTES;

    public function __construct(
        private ChannelTypes $channelTypes,
    ) {}

    public function isValid(string $name): bool
    {
        if (strlen($name) < 2 || strlen($name) > self::MAX_LENGTH) {
            return false;
        }

        if (! $this->channelTypes->isChannelTarget($name)) {
            return false;
        }

        return strpbrk($name, " ,\x07") === false;
    }
}
