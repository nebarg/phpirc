<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelModeResponseFactory;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Mode\ModeAction;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelModeResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_current_mode_and_creation_time_responses(): void
    {
        $channel = new Channel('#php', new DateTimeImmutable('@1788782400'));

        [$modeResponse, $creationTimeResponse] = $this->factory()->createCurrentModeResponses('John', $channel);

        $this->assertSame('324', $modeResponse->command);
        $this->assertSame(['John', '#php', '+nt'], $modeResponse->parameters);
        $this->assertSame('329', $creationTimeResponse->command);
        $this->assertSame(['John', '#php', '1788782400'], $creationTimeResponse->parameters);
    }

    #[Test]
    public function it_creates_a_mode_change_message_and_groups_mode_actions(): void
    {
        $response = $this->factory()->createChangedMessage(
            'John',
            new Channel('#php'),
            [
                new ChannelModeChange(ModeAction::Add, ChannelMode::Moderated),
                new ChannelModeChange(ModeAction::Remove, ChannelMode::NoExternalMessages),
                new MembershipModeChange(ModeAction::Add, MembershipMode::Operator, 'Jane'),
                new MembershipModeChange(ModeAction::Add, MembershipMode::Voice, 'Alex'),
            ],
        );

        $this->assertSame('John', $response->source);
        $this->assertSame('MODE', $response->command);
        $this->assertSame(['#php', '+m-n+ov', 'Jane', 'Alex'], $response->parameters);
    }

    private function factory(): ChannelModeResponseFactory
    {
        return new ChannelModeResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }
}
