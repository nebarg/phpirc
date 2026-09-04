<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\WhoResponseFactory;
use PhpIrc\Irc\Config\ServerName;
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
    public function it_creates_the_end_of_who_numeric(): void
    {
        $message = $this->factory()->createEndResponse('Jane', '#php');

        $this->assertSame('irc.test', $message->source);
        $this->assertSame('315', $message->command);
        $this->assertSame(['Jane', '#php', 'End of WHO list'], $message->parameters);
    }

    private function factory(): WhoResponseFactory
    {
        $serverName = new ServerName('irc.test');

        return new WhoResponseFactory(
            serverName: $serverName,
            responses: new NumericResponseFactory($serverName),
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
