<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Policy;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Policy\ClientVisibilityPolicy;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientVisibilityPolicyTest extends TestCase
{
    #[Test]
    public function a_visible_client_can_be_seen_without_a_shared_channel(): void
    {
        $policy = new ClientVisibilityPolicy(new ChannelRegistry(new AsciiCaseMapper()));

        $this->assertTrue($policy->canSee(new Client(), new Client()));
    }

    #[Test]
    public function a_client_can_see_themselves_while_invisible(): void
    {
        $client = new Client();
        $client->enableMode(UserMode::Invisible);
        $policy = new ClientVisibilityPolicy(new ChannelRegistry(new AsciiCaseMapper()));

        $this->assertTrue($policy->canSee($client, $client));
    }

    #[Test]
    public function an_invisible_client_cannot_be_seen_without_a_shared_channel(): void
    {
        $client = new Client();
        $client->enableMode(UserMode::Invisible);
        $policy = new ClientVisibilityPolicy(new ChannelRegistry(new AsciiCaseMapper()));

        $this->assertFalse($policy->canSee(new Client(), $client));
    }

    #[Test]
    public function an_invisible_client_can_be_seen_in_a_shared_channel(): void
    {
        $requester = new Client();
        $client = new Client();
        $client->enableMode(UserMode::Invisible);
        $channels = new ChannelRegistry(new AsciiCaseMapper());
        $channels->join('#php', $requester);
        $channels->join('#php', $client);
        $policy = new ClientVisibilityPolicy($channels);

        $this->assertTrue($policy->canSee($requester, $client));
    }

    #[Test]
    public function a_names_query_only_reveals_invisible_clients_to_members_of_that_channel(): void
    {
        $requester = new Client();
        $client = new Client();
        $client->enableMode(UserMode::Invisible);
        $channels = new ChannelRegistry(new AsciiCaseMapper());
        $queriedChannel = $channels->join('#private', $client);
        $channels->join('#shared', $requester);
        $channels->join('#shared', $client);
        $policy = new ClientVisibilityPolicy($channels);

        $this->assertTrue($policy->canSee($requester, $client));
        $this->assertFalse($policy->canSeeInChannel($requester, $client, $queriedChannel));

        $queriedChannel->join($requester);

        $this->assertTrue($policy->canSeeInChannel($requester, $client, $queriedChannel));
    }
}
