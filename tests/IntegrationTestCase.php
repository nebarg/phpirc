<?php

declare(strict_types=1);

namespace Tests;

use Tempest\Discovery\DiscoveryLocation;
use Tempest\Framework\Testing\IntegrationTest;

abstract class IntegrationTestCase extends IntegrationTest
{
    protected string $root = __DIR__ . '/../';

    /** @return list<DiscoveryLocation> */
    protected function discoverTestLocations(): array
    {
        return [];
    }
}
