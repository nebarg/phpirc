<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Mode;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\ChannelModeChange;
use PhpIrc\Irc\Channel\Mode\ChannelModeChanger;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Mode\MembershipModeChange;
use PhpIrc\Irc\Channel\Mode\ModeChangeFailureReason;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Mode\ModeAction;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ChannelModeChangerTest extends TestCase
{
    #[Test]
    public function it_parses_and_applies_channel_and_membership_mode_changes(): void
    {
        [$changer, $clients] = $this->changer();
        $channel = new Channel('#php');
        $channel->join($this->register($clients, 'John'));
        $jane = $this->register($clients, 'Jane');
        $janeMembership = $channel->join($jane);

        $result = $changer->apply($channel, '+mxov', ['jAnE', 'jAnE']);

        $this->assertSame(['x'], $result->unknownModes);
        $this->assertSame([], $result->failures);
        $this->assertCount(3, $result->appliedChanges);
        $this->assertInstanceOf(ChannelModeChange::class, $result->appliedChanges[0]);
        $this->assertSame(ChannelMode::Moderated, $result->appliedChanges[0]->mode);
        $this->assertInstanceOf(MembershipModeChange::class, $result->appliedChanges[1]);
        $this->assertSame(ModeAction::Add, $result->appliedChanges[1]->action);
        $this->assertSame(MembershipMode::Operator, $result->appliedChanges[1]->mode);
        $this->assertSame('Jane', $result->appliedChanges[1]->nickname);
        $this->assertInstanceOf(MembershipModeChange::class, $result->appliedChanges[2]);
        $this->assertSame(MembershipMode::Voice, $result->appliedChanges[2]->mode);
        $this->assertSame('Jane', $result->appliedChanges[2]->nickname);
        $this->assertTrue($channel->hasMode(ChannelMode::Moderated));
        $this->assertTrue($janeMembership->has(MembershipMode::Operator));
        $this->assertTrue($janeMembership->has(MembershipMode::Voice));
    }

    #[Test]
    public function it_reports_an_unknown_nickname_without_applying_the_change(): void
    {
        [$changer] = $this->changer();

        $result = $changer->apply(new Channel('#php'), '+o', ['Missing']);

        $this->assertSame([], $result->appliedChanges);
        $this->assertCount(1, $result->failures);
        $this->assertSame('Missing', $result->failures[0]->nickname);
        $this->assertSame(ModeChangeFailureReason::NoSuchNickname, $result->failures[0]->reason);
    }

    #[Test]
    public function it_treats_an_unregistered_client_as_an_unknown_nickname(): void
    {
        [$changer, $clients] = $this->changer();
        $client = new Client();
        $clients->register($client, new RecordingConnection());
        $clients->claimNickname($client, 'Jane');

        $result = $changer->apply(new Channel('#php'), '+v', ['Jane']);

        $this->assertCount(1, $result->failures);
        $this->assertSame(ModeChangeFailureReason::NoSuchNickname, $result->failures[0]->reason);
    }

    #[Test]
    public function it_reports_a_known_client_outside_the_channel_with_their_canonical_nickname(): void
    {
        [$changer, $clients] = $this->changer();
        $this->register($clients, 'Jane');

        $result = $changer->apply(new Channel('#php'), '+v', ['jAnE']);

        $this->assertSame([], $result->appliedChanges);
        $this->assertCount(1, $result->failures);
        $this->assertSame('Jane', $result->failures[0]->nickname);
        $this->assertSame(ModeChangeFailureReason::UserNotInChannel, $result->failures[0]->reason);
    }

    #[Test]
    public function it_omits_changes_that_do_not_alter_channel_state(): void
    {
        [$changer] = $this->changer();

        $result = $changer->apply(new Channel('#php'), '+nt-m', []);

        $this->assertSame([], $result->appliedChanges);
        $this->assertSame([], $result->failures);
        $this->assertSame([], $result->unknownModes);
    }

    /** @return array{ChannelModeChanger, ClientRegistry} */
    private function changer(): array
    {
        $clients = new ClientRegistry(new AsciiCaseMapper());

        return [new ChannelModeChanger(new ModeChangeParser(), $clients), $clients];
    }

    private function register(ClientRegistry $registry, string $nickname): Client
    {
        $client = new Client();
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();
        $registry->register($client, new RecordingConnection());
        $registry->claimNickname($client, $nickname);

        return $client;
    }
}
