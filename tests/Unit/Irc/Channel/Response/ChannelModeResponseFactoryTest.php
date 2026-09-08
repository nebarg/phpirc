<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Response;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\ModeChangeFailure;
use PhpIrc\Irc\Channel\Mode\ModeChangeFailureReason;
use PhpIrc\Irc\Channel\Response\ChannelModeResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Mode\ModeAction;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
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

    #[Test]
    public function it_creates_unknown_channel_and_mode_responses(): void
    {
        $factory = $this->factory();

        $unknownChannel = $factory->createUnknownChannelResponse('John', '#missing');
        $unknownMode = $factory->createUnknownModeResponse('John', 'x');

        $this->assertSame('403', $unknownChannel->command);
        $this->assertSame(['John', '#missing', 'No such channel'], $unknownChannel->parameters);
        $this->assertSame('472', $unknownMode->command);
        $this->assertSame(['John', 'x', 'is unknown mode char to me'], $unknownMode->parameters);
    }

    #[Test]
    public function it_creates_an_unknown_nickname_change_failure_response(): void
    {
        $response = $this->factory()->createChangeFailureResponse(
            target: 'John',
            channel: new Channel('#php'),
            failure: new ModeChangeFailure('Missing', ModeChangeFailureReason::NoSuchNickname),
        );

        $this->assertSame('401', $response->command);
        $this->assertSame(['John', 'Missing', 'No such nick/channel'], $response->parameters);
    }

    #[Test]
    public function it_creates_a_user_not_in_channel_change_failure_response(): void
    {
        $response = $this->factory()->createChangeFailureResponse(
            target: 'John',
            channel: new Channel('#php'),
            failure: new ModeChangeFailure('Jane', ModeChangeFailureReason::UserNotInChannel),
        );

        $this->assertSame('441', $response->command);
        $this->assertSame(
            ['John', 'Jane', '#php', "They aren't on that channel"],
            $response->parameters,
        );
    }

    private function factory(): ChannelModeResponseFactory
    {
        $responses = new NumericResponseFactory(new ServerName('irc.test'));

        return new ChannelModeResponseFactory(
            $responses,
            new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
        );
    }
}
