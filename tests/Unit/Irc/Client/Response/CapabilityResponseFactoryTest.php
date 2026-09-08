<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Response;

use PhpIrc\Irc\Client\Response\CapabilityResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CapabilityResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_supported_capabilities_response(): void
    {
        $response = $this->factory()->createSupportedCapabilitiesResponse('John', 'multi-prefix');

        $this->assertSame('irc.test', $response->source);
        $this->assertSame('CAP', $response->command);
        $this->assertSame(['John', 'LS', 'multi-prefix'], $response->parameters);
    }

    #[Test]
    public function it_creates_the_enabled_capabilities_response(): void
    {
        $response = $this->factory()->createEnabledCapabilitiesResponse('John', 'multi-prefix');

        $this->assertSame('irc.test', $response->source);
        $this->assertSame('CAP', $response->command);
        $this->assertSame(['John', 'LIST', 'multi-prefix'], $response->parameters);
    }

    #[Test]
    public function it_creates_the_rejected_capabilities_response(): void
    {
        $response = $this->factory()->createRejectedCapabilitiesResponse('John', 'multi-prefix');

        $this->assertSame('irc.test', $response->source);
        $this->assertSame('CAP', $response->command);
        $this->assertSame(['John', 'NAK', 'multi-prefix'], $response->parameters);
    }

    #[Test]
    public function it_creates_an_invalid_subcommand_response(): void
    {
        $response = $this->factory()->createInvalidSubcommandResponse('John', 'NOPE');

        $this->assertSame('irc.test', $response->source);
        $this->assertSame('410', $response->command);
        $this->assertSame(['John', 'NOPE', 'Invalid CAP command'], $response->parameters);
    }

    private function factory(): CapabilityResponseFactory
    {
        $serverName = new ServerName('irc.test');

        return new CapabilityResponseFactory(
            $serverName,
            new NumericErrorResponseFactory(new NumericResponseFactory($serverName), new ByteStringTruncator()),
        );
    }
}
