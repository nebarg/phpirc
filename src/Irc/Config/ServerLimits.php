<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

final readonly class ServerLimits
{
    public const int MAX_SERVER_NAME_BYTES = 63;

    public const int MAX_NICKNAME_BYTES = 30;

    public const int MAX_CHANNEL_NAME_BYTES = 64;

    public const int MAX_TOPIC_BYTES = 307;

    public const int MAX_USERNAME_BYTES = 18;

    public const int MAX_HOSTNAME_BYTES = 63;

    public const int MAX_REAL_NAME_BYTES = 128;

    public function truncateTopic(string $topic): string
    {
        return $this->truncate($topic, self::MAX_TOPIC_BYTES);
    }

    public function truncateUsername(string $username): string
    {
        return $this->truncate($username, self::MAX_USERNAME_BYTES);
    }

    public function truncateHostname(string $hostname): string
    {
        return $this->truncate($hostname, self::MAX_HOSTNAME_BYTES);
    }

    public function truncateRealName(string $realName): string
    {
        return $this->truncate($realName, self::MAX_REAL_NAME_BYTES);
    }

    private function truncate(string $value, int $maximumBytes): string
    {
        if (strlen($value) <= $maximumBytes) {
            return $value;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return substr($value, 0, $maximumBytes);
        }

        return mb_strcut($value, 0, $maximumBytes, 'UTF-8');
    }
}
