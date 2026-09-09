<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Registration\ClientRegistration;

final class Client
{
    /** @var array<string, UserMode> */
    private array $modes = [];

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

    /**
     * Updates the client after its nickname has been secured in the registry.
     *
     * @internal Nickname changes must go through {@see ClientRegistry::claimNickname()} so the
     * registry's nickname index and the client cannot diverge.
     */
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

    public function enableMode(UserMode $mode): bool
    {
        if ($this->hasMode($mode)) {
            return false;
        }

        $this->modes[$mode->value] = $mode;

        return true;
    }

    public function disableMode(UserMode $mode): bool
    {
        if (! $this->hasMode($mode)) {
            return false;
        }

        unset($this->modes[$mode->value]);

        return true;
    }

    public function hasMode(UserMode $mode): bool
    {
        return isset($this->modes[$mode->value]);
    }

    /** @return list<UserMode> */
    public function modes(): array
    {
        return array_values($this->modes);
    }
}
