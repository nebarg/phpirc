<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Client\MotdFileLoader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MotdFileLoaderTest extends TestCase
{
    #[Test]
    public function it_returns_an_empty_motd_when_no_file_is_configured(): void
    {
        $motd = new MotdFileLoader()->load(null);

        $this->assertTrue($motd->isEmpty());
    }

    #[Test]
    public function it_returns_an_empty_motd_when_the_file_does_not_exist(): void
    {
        $motd = new MotdFileLoader()->load('/missing/phpirc-motd');

        $this->assertTrue($motd->isEmpty());
    }

    #[Test]
    public function it_loads_lines_and_preserves_blank_lines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpirc-motd-');
        $this->assertIsString($path);

        try {
            file_put_contents($path, "Welcome\n\nHave fun\n");

            $motd = new MotdFileLoader()->load($path);

            $this->assertSame(['Welcome', '', 'Have fun'], $motd->lines);
        } finally {
            unlink($path);
        }
    }
}
