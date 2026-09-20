<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Client\HostCloak;
use PhpIrc\Irc\Config\HostCloakConfig;
use PhpIrc\Irc\Config\ServerLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HostCloakTest extends TestCase
{
    #[Test]
    public function it_leaves_the_address_alone_when_it_is_switched_off(): void
    {
        $cloak = new HostCloak(new HostCloakConfig());

        $this->assertSame('203.0.113.10', $cloak->mask('203.0.113.10'));
    }

    #[Test]
    public function it_replaces_the_address_with_a_digest_and_the_suffix(): void
    {
        $masked = $this->cloak()->mask('203.0.113.10');

        $this->assertMatchesRegularExpression('~\A[0-9a-f]{16}\.phpirc\z~', $masked);
        $this->assertStringNotContainsString('203.0.113.10', $masked);
    }

    #[Test]
    public function it_gives_the_same_address_the_same_mask_every_time(): void
    {
        $cloak = $this->cloak();

        $this->assertSame($cloak->mask('203.0.113.10'), $cloak->mask('203.0.113.10'));
    }

    #[Test]
    public function it_gives_neighbouring_addresses_unrelated_masks(): void
    {
        $cloak = $this->cloak();

        $this->assertNotSame($cloak->mask('203.0.113.10'), $cloak->mask('203.0.113.11'));
    }

    #[Test]
    public function it_gives_the_same_address_a_different_mask_under_a_different_secret(): void
    {
        $this->assertNotSame(
            $this->cloak($this->secret())->mask('203.0.113.10'),
            $this->cloak($this->secret())->mask('203.0.113.10'),
        );
    }

    #[Test]
    public function it_masks_an_ipv6_address(): void
    {
        $masked = $this->cloak()->mask('2001:db8::1');

        $this->assertMatchesRegularExpression('~\A[0-9a-f]{16}\.phpirc\z~', $masked);
    }

    #[Test]
    public function it_keeps_the_mask_within_the_hostname_limit(): void
    {
        $cloak = new HostCloak(new HostCloakConfig(
            enabled: true,
            secret: $this->secret(),
            suffix: str_repeat('a', HostCloakConfig::MAX_SUFFIX_BYTES),
        ));

        $this->assertLessThanOrEqual(
            ServerLimits::MAX_HOSTNAME_BYTES,
            strlen($cloak->mask('203.0.113.10')),
        );
    }

    #[Test]
    public function it_has_nothing_to_mask_when_the_address_is_empty(): void
    {
        $this->assertSame('', $this->cloak()->mask(''));
    }

    private function cloak(?string $secret = null): HostCloak
    {
        return new HostCloak(new HostCloakConfig(
            enabled: true,
            secret: $secret ?? $this->secret(),
            suffix: 'phpirc',
        ));
    }

    private function secret(): string
    {
        return bin2hex(random_bytes(16));
    }
}
