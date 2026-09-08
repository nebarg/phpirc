<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Client\MotdFileLoader;
use PhpIrc\Irc\Config\ServerConfig;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class MotdWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_loads_the_configured_motd_file_once(): void
    {
        $config = $this->container->get(ServerConfig::class);
        $first = $this->container->get(Motd::class);
        $second = $this->container->get(Motd::class);
        $expected = new MotdFileLoader()->load($config->motdFile);

        $this->assertSame($expected->lines, $first->lines);
        $this->assertSame($first, $second);
    }
}
