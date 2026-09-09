<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Mode;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Mode\UserModeChanger;
use PhpIrc\Irc\Mode\ModeAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserModeChangerTest extends TestCase
{
    #[Test]
    public function it_applies_supported_mode_changes(): void
    {
        $client = new Client();
        $changer = new UserModeChanger();

        $result = $changer->apply($client, '+i-i');

        $this->assertCount(2, $result->appliedChanges);
        $this->assertSame(ModeAction::Add, $result->appliedChanges[0]->action);
        $this->assertSame(UserMode::Invisible, $result->appliedChanges[0]->mode);
        $this->assertSame(ModeAction::Remove, $result->appliedChanges[1]->action);
        $this->assertSame(UserMode::Invisible, $result->appliedChanges[1]->mode);
        $this->assertFalse($result->hasUnknownModes);
        $this->assertFalse($client->hasMode(UserMode::Invisible));
    }

    #[Test]
    public function it_ignores_changes_that_do_not_alter_the_client(): void
    {
        $client = new Client();
        $client->enableMode(UserMode::Invisible);

        $result = new UserModeChanger()->apply($client, '+ii');

        $this->assertSame([], $result->appliedChanges);
        $this->assertFalse($result->hasUnknownModes);
        $this->assertTrue($client->hasMode(UserMode::Invisible));
    }

    #[Test]
    public function it_flags_unknown_modes_and_applies_known_modes(): void
    {
        $client = new Client();

        $result = new UserModeChanger()->apply($client, '+xi');

        $this->assertTrue($result->hasUnknownModes);
        $this->assertCount(1, $result->appliedChanges);
        $this->assertSame(UserMode::Invisible, $result->appliedChanges[0]->mode);
        $this->assertTrue($client->hasMode(UserMode::Invisible));
    }

    #[Test]
    public function it_flags_a_mode_without_an_action_as_unknown(): void
    {
        $client = new Client();

        $result = new UserModeChanger()->apply($client, 'i');

        $this->assertTrue($result->hasUnknownModes);
        $this->assertSame([], $result->appliedChanges);
        $this->assertFalse($client->hasMode(UserMode::Invisible));
    }

    #[Test]
    public function it_ignores_action_signs_without_modes(): void
    {
        $result = new UserModeChanger()->apply(new Client(), '+-');

        $this->assertSame([], $result->appliedChanges);
        $this->assertFalse($result->hasUnknownModes);
    }
}
