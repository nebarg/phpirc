<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Registration;

use PhpIrc\Irc\Client\Registration\ClientRegistration;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientRegistrationTest extends TestCase
{
    #[Test]
    public function it_completes_a_pending_registration_only_once(): void
    {
        $registration = new ClientRegistration();

        $this->assertFalse($registration->isComplete());
        $this->assertTrue($registration->complete());
        $this->assertTrue($registration->isComplete());
        $this->assertFalse($registration->complete());
    }

    #[Test]
    public function it_does_not_complete_while_capability_negotiation_is_active(): void
    {
        $registration = new ClientRegistration();

        $registration->suspendForCapabilityNegotiation();

        $this->assertFalse($registration->complete());
        $this->assertFalse($registration->isComplete());
    }

    #[Test]
    public function it_can_complete_after_capability_negotiation_ends(): void
    {
        $registration = new ClientRegistration();
        $registration->suspendForCapabilityNegotiation();

        $registration->resumeAfterCapabilityNegotiation();

        $this->assertTrue($registration->complete());
        $this->assertTrue($registration->isComplete());
    }

    #[Test]
    public function it_cannot_reopen_a_completed_registration(): void
    {
        $registration = new ClientRegistration();
        $registration->complete();

        $registration->suspendForCapabilityNegotiation();
        $registration->resumeAfterCapabilityNegotiation();

        $this->assertTrue($registration->isComplete());
        $this->assertFalse($registration->complete());
    }
}
