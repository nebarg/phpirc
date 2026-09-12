<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Capability;

use PhpIrc\Irc\Client\Capability\Capability;
use PhpIrc\Irc\Client\Capability\ClientCapabilities;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientCapabilitiesTest extends TestCase
{
    #[Test]
    public function it_enables_and_disables_capabilities_idempotently(): void
    {
        $capabilities = new ClientCapabilities();

        $this->assertTrue($capabilities->enable(Capability::ServerTime));
        $this->assertFalse($capabilities->enable(Capability::ServerTime));
        $this->assertTrue($capabilities->has(Capability::ServerTime));
        $this->assertSame([Capability::ServerTime], $capabilities->all());

        $this->assertTrue($capabilities->disable(Capability::ServerTime));
        $this->assertFalse($capabilities->disable(Capability::ServerTime));
        $this->assertFalse($capabilities->has(Capability::ServerTime));
        $this->assertSame([], $capabilities->all());
    }
}
