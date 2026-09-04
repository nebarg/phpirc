<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Client\Registration\ClientRegistration;

final class Client
{
    public private(set) ?string $nickname = null;

    public private(set) ?string $username = null;

    public private(set) ?string $realName = null;

    public private(set) ClientRegistration $registration;

    public function __construct(
        public readonly string $hostname = 'localhost',
    ) {
        $this->registration = new ClientRegistration();
    }

    public function completeRegistrationIfReady(): bool
    {
        if ($this->nickname === null || $this->username === null || $this->realName === null) {
            return false;
        }

        return $this->registration->complete();
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = $nickname;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setRealName(string $realName): void
    {
        $this->realName = $realName;
    }
}
