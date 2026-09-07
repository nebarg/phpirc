<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use PhpIrc\Irc\Protocol\ByteStringTruncator;

final readonly class ServerLimits
{
    public const int MAX_SERVER_NAME_BYTES = 63;

    public const int MAX_NICKNAME_BYTES = 30;

    public const int MAX_CHANNEL_NAME_BYTES = 64;

    public const int MAX_TOPIC_BYTES = 307;

    public const int MAX_USERNAME_BYTES = 18;

    public const int MAX_HOSTNAME_BYTES = 63;

    public const int MAX_REAL_NAME_BYTES = 128;

    public const int MAX_COMMAND_BYTES = 32;

    public function __construct(
        private ByteStringTruncator $strings,
    ) {}

    public function truncateTopic(string $topic): string
    {
        return $this->strings->truncate($topic, self::MAX_TOPIC_BYTES);
    }

    public function truncateUsername(string $username): string
    {
        return $this->strings->truncate($username, self::MAX_USERNAME_BYTES);
    }

    public function truncateHostname(string $hostname): string
    {
        return $this->strings->truncate($hostname, self::MAX_HOSTNAME_BYTES);
    }

    public function truncateRealName(string $realName): string
    {
        return $this->strings->truncate($realName, self::MAX_REAL_NAME_BYTES);
    }
}
