<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\MessageSize;

final readonly class ServerLimits
{
    /** Fixed bytes in `:<server> 301 <target> <nickname> :<message>\r\n`, excluding variable fields. */
    private const int AWAY_REPLY_SYNTAX_BYTES = 11;

    /** Fixed bytes in `:<server> 372 <nickname> :- <line>\r\n`, excluding the variable fields. */
    private const int MOTD_LINE_SYNTAX_BYTES = 12;

    public const int MAX_SERVER_NAME_BYTES = 63;

    public const int MAX_NICKNAME_BYTES = 30;

    public const int MAX_CHANNEL_NAME_BYTES = 64;

    public const int MAX_TOPIC_BYTES = 307;

    public const int MAX_USERNAME_BYTES = 18;

    public const int MAX_HOSTNAME_BYTES = 63;

    public const int MAX_REAL_NAME_BYTES = 128;

    public const int MAX_COMMAND_BYTES = 32;

    // @mago-format-ignore-next
    public const int MAX_AWAY_MESSAGE_BYTES = MessageSize::MAX_BYTES
        - self::MAX_SERVER_NAME_BYTES
        - (self::MAX_NICKNAME_BYTES * 2)
        - self::AWAY_REPLY_SYNTAX_BYTES;

    // @mago-format-ignore-next
    public const int MAX_MOTD_LINE_BYTES = MessageSize::MAX_BYTES
        - self::MAX_SERVER_NAME_BYTES
        - self::MAX_NICKNAME_BYTES
        - self::MOTD_LINE_SYNTAX_BYTES;

    public function __construct(
        private ByteStringTruncator $strings,
    ) {}

    public function truncateTopic(string $topic): string
    {
        return $this->strings->truncate($topic, self::MAX_TOPIC_BYTES);
    }

    public function truncateAwayMessage(string $message): string
    {
        return $this->strings->truncate($message, self::MAX_AWAY_MESSAGE_BYTES);
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
