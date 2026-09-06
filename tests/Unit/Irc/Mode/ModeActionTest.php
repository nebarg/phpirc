<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Mode;

use PhpIrc\Irc\Mode\ModeAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModeActionTest extends TestCase
{
    #[Test]
    public function it_defines_add_and_remove_signs(): void
    {
        $this->assertSame('+', ModeAction::Add->value);
        $this->assertSame('-', ModeAction::Remove->value);
    }
}
