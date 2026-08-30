<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class ChannelRegistryWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_registers_the_channel_registry_as_a_singleton(): void
    {
        $first = $this->container->get(ChannelRegistry::class);
        $second = $this->container->get(ChannelRegistry::class);

        $this->assertSame($first, $second);
    }
}
