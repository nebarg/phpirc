<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Network;

final class ClientRegistration
{
    private RegistrationStatus $status = RegistrationStatus::Pending;

    public function suspendForCapabilityNegotiation(): void
    {
        if ($this->status === RegistrationStatus::Pending) {
            $this->status = RegistrationStatus::WaitingForCapEnd;
        }
    }

    public function resumeAfterCapabilityNegotiation(): void
    {
        if ($this->status === RegistrationStatus::WaitingForCapEnd) {
            $this->status = RegistrationStatus::Pending;
        }
    }

    public function complete(): bool
    {
        if ($this->status !== RegistrationStatus::Pending) {
            return false;
        }

        $this->status = RegistrationStatus::Complete;

        return true;
    }

    public function isComplete(): bool
    {
        return $this->status === RegistrationStatus::Complete;
    }
}
