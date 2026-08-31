<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Transport\ClientConnectionRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class ClientConnectionRegistryWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_registers_the_client_connection_registry_as_a_singleton(): void
    {
        $first = $this->container->get(ClientConnectionRegistry::class);
        $second = $this->container->get(ClientConnectionRegistry::class);

        $this->assertSame($first, $second);
    }
}
