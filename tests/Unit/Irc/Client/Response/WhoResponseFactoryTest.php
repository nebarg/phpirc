<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Response\WhoResponseFactory;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhoResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_reply_for_an_exact_client(): void
    {
        $message = $this->factory()->createClientReply(
            target: 'Jane',
            client: $this->client('John'),
        );

        $this->assertSame('irc.test', $message->source);
        $this->assertSame('352', $message->command);
        $this->assertSame(
            ['Jane', '*', 'john', '203.0.113.10', 'irc.test', 'John', 'H', '0 John Doe'],
            $message->parameters,
        );
    }

    #[Test]
    public function it_includes_the_channel_and_operator_flag_for_a_channel_member(): void
    {
        $client = $this->client('John');
        $channel = new Channel('#php');
        $membership = $channel->join($client);

        $message = $this->factory()->createChannelMemberReply(
            target: 'Jane',
            channel: $channel,
            membership: $membership,
        );

        $this->assertSame(
            ['Jane', '#php', 'john', '203.0.113.10', 'irc.test', 'John', 'H@', '0 John Doe'],
            $message->parameters,
        );
    }

    #[Test]
    public function it_includes_the_voice_flag_for_a_voiced_channel_member(): void
    {
        $operator = $this->client('John');
        $client = $this->client('Jane');
        $channel = new Channel('#php');
        $channel->join($operator);
        $membership = $channel->join($client);
        $membership->grant(MembershipMode::Voice);

        $message = $this->factory()->createChannelMemberReply(
            target: 'John',
            channel: $channel,
            membership: $membership,
        );

        $this->assertSame(
            ['John', '#php', 'jane', '203.0.113.10', 'irc.test', 'Jane', 'H+', '0 Jane Doe'],
            $message->parameters,
        );
    }

    #[Test]
    public function it_marks_an_away_client_as_gone(): void
    {
        $client = $this->client('John');
        $client->markAway('Gone for lunch');

        $message = $this->factory()->createClientReply(
            target: 'Jane',
            client: $client,
        );

        $this->assertSame('G', $message->parameter(6));
    }

    #[Test]
    public function it_creates_the_missing_mask_error(): void
    {
        $message = $this->factory()->createMissingMaskResponse('Jane');

        $this->assertSame('irc.test', $message->source);
        $this->assertSame('461', $message->command);
        $this->assertSame(['Jane', 'WHO', 'Not enough parameters'], $message->parameters);
    }

    #[Test]
    public function it_creates_the_end_of_who_numeric(): void
    {
        $message = $this->factory()->createEndOfWhoResponse('Jane', '#php');

        $this->assertSame('irc.test', $message->source);
        $this->assertSame('315', $message->command);
        $this->assertSame(['Jane', '#php', 'End of WHO list'], $message->parameters);
    }

    #[Test]
    public function it_keeps_channel_member_replies_within_the_message_size_limit(): void
    {
        $serverName = new ServerName('irc.' . str_repeat('s', 59));
        $client = new Client(str_repeat('h', ServerLimits::MAX_HOSTNAME_BYTES));
        $client->setNickname(str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES));
        $client->setUsername(str_repeat('u', ServerLimits::MAX_USERNAME_BYTES));
        $client->setRealName(str_repeat('r', ServerLimits::MAX_REAL_NAME_BYTES));
        $channel = new Channel('#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1));
        $membership = $channel->join($client);

        $message = $this->factory($serverName)->createChannelMemberReply(
            target: str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES),
            channel: $channel,
            membership: $membership,
        );

        $this->assertTrue(new MessageSize(new MessageEncoder())->fits($message));
    }

    private function factory(?ServerName $serverName = null): WhoResponseFactory
    {
        $serverName ??= new ServerName('irc.test');

        $responses = new NumericResponseFactory($serverName);

        return new WhoResponseFactory(
            serverName: $serverName,
            responses: $responses,
            errors: new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
        );
    }

    private function client(string $nickname): Client
    {
        $client = new Client('203.0.113.10');
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");

        return $client;
    }
}
