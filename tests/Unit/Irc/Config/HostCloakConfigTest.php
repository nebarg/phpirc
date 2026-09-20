<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\HostCloakConfig;
use PhpIrc\Irc\Config\ServerLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HostCloakConfigTest extends TestCase
{
    #[Test]
    public function it_is_disabled_without_any_settings(): void
    {
        $config = new HostCloakConfig();

        $this->assertFalse($config->enabled);
    }

    #[Test]
    public function it_exposes_its_settings_when_enabled(): void
    {
        $secret = bin2hex(random_bytes(16));
        $config = new HostCloakConfig(enabled: true, secret: $secret, suffix: 'phpirc');

        $this->assertTrue($config->enabled);
        $this->assertSame($secret, $config->secret);
        $this->assertSame('phpirc', $config->suffix);
    }

    #[Test]
    public function it_leaves_a_disabled_cloak_unvalidated(): void
    {
        $config = new HostCloakConfig(enabled: false, secret: '', suffix: '');

        $this->assertFalse($config->enabled);
    }

    #[Test]
    public function it_keeps_a_cloaked_host_within_the_hostname_limit(): void
    {
        $longest = HostCloakConfig::DIGEST_LENGTH + 1 + HostCloakConfig::MAX_SUFFIX_BYTES;

        $this->assertSame(ServerLimits::MAX_HOSTNAME_BYTES, $longest);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'no secret' => ['', 'cloak', 'A host cloak secret is required'];
        yield 'empty suffix' => ['shh', '', 'Host cloak suffix must be letters'];
        yield 'suffix with a space' => ['shh', 'my cloak', 'Host cloak suffix must be letters'];
        yield 'suffix with a slash' => ['shh', 'cloak/x', 'Host cloak suffix must be letters'];
        yield 'suffix opening with a dot' => ['shh', '.cloak', 'Host cloak suffix must be letters'];
        yield 'suffix closing with a hyphen' => ['shh', 'cloak-', 'Host cloak suffix must be letters'];
        yield 'suffix too long' => [
            'shh',
            str_repeat('a', HostCloakConfig::MAX_SUFFIX_BYTES + 1),
            'Host cloak suffix cannot be longer than',
        ];
    }

    #[Test]
    #[DataProvider('invalidSettings')]
    public function it_rejects_settings_it_cannot_cloak_with(
        string $secret,
        string $suffix,
        string $expectedMessage,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~' . preg_quote($expectedMessage, '~') . '~');

        new HostCloakConfig(enabled: true, secret: $secret, suffix: $suffix);
    }
}
