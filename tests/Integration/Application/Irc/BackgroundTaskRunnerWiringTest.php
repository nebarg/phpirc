<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Transport\Amp\AmpBackgroundTaskRunner;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class BackgroundTaskRunnerWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_uses_amp_for_background_tasks(): void
    {
        $this->assertInstanceOf(
            AmpBackgroundTaskRunner::class,
            $this->container->get(BackgroundTaskRunner::class),
        );
    }
}
