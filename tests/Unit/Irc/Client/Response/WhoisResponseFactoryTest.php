<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Client\Response\WhoisResponseFactory;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhoisResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_core_responses_for_a_client_without_channels(): void
    {
        $responses = $this->factory()->createWhoisResponses(
            target: 'Jane',
            requestedNickname: 'jOhN',
            client: $this->client('John'),
            channels: [],
        );

        $this->assertSame(['311', '312', '318'], array_column($responses, 'command'));
        $this->assertSame(
            ['Jane', 'John', 'john', '203.0.113.10', '*', 'John Doe'],
            $responses[0]->parameters,
        );
        $this->assertSame(
            ['Jane', 'John', 'irc.test', 'TestNet'],
            $responses[1]->parameters,
        );
        $this->assertSame(
            ['Jane', 'jOhN', 'End of /WHOIS list'],
            $responses[2]->parameters,
        );

        foreach ($responses as $response) {
            $this->assertSame('irc.test', $response->source);
        }
    }

    #[Test]
    public function it_lists_channels_with_the_clients_highest_membership_prefix(): void
    {
        $john = $this->client('John');
        $jane = $this->client('Jane');
        $php = new Channel('#php');
        $general = new Channel('#general');
        $php->join($john);
        $general->join($jane);
        $generalMembership = $general->join($john);
        $generalMembership->grant(MembershipMode::Voice);

        $responses = $this->factory()->createWhoisResponses(
            target: 'Jane',
            requestedNickname: 'John',
            client: $john,
            channels: [$php, $general],
        );

        $this->assertSame(['311', '312', '319', '318'], array_column($responses, 'command'));
        $this->assertSame(
            ['Jane', 'John', '@#php +#general'],
            $responses[2]->parameters,
        );
    }

    #[Test]
    public function it_includes_the_clients_away_message(): void
    {
        $client = $this->client('John');
        $client->markAway('Gone for lunch');

        $responses = $this->factory()->createWhoisResponses(
            target: 'Jane',
            requestedNickname: 'John',
            client: $client,
            channels: [],
        );

        $this->assertSame(['311', '312', '301', '318'], array_column($responses, 'command'));
        $this->assertSame(['Jane', 'John', 'Gone for lunch'], $responses[2]->parameters);
    }

    #[Test]
    public function it_ignores_channels_the_client_has_not_joined(): void
    {
        $responses = $this->factory()->createWhoisResponses(
            target: 'Jane',
            requestedNickname: 'John',
            client: $this->client('John'),
            channels: [new Channel('#php')],
        );

        $this->assertSame(['311', '312', '318'], array_column($responses, 'command'));
    }

    #[Test]
    public function it_splits_channel_lists_to_fit_the_message_size_limit(): void
    {
        $serverName = new ServerName(str_repeat('s', ServerLimits::MAX_SERVER_NAME_BYTES));
        $client = new Client(str_repeat('h', ServerLimits::MAX_HOSTNAME_BYTES));
        $client->setNickname(str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES));
        $client->setUsername(str_repeat('u', ServerLimits::MAX_USERNAME_BYTES));
        $client->setRealName(str_repeat('r', ServerLimits::MAX_REAL_NAME_BYTES));
        $channels = [];
        $expectedChannelNames = [];

        for ($index = 0; $index < 12; $index++) {
            $channelName = '#' . str_pad((string) $index, ServerLimits::MAX_CHANNEL_NAME_BYTES - 1, 'c');
            $channel = new Channel($channelName);
            $channel->join($client);
            $channels[] = $channel;
            $expectedChannelNames[] = '@' . $channelName;
        }

        $messageSize = new MessageSize(new MessageEncoder());
        $responses = $this->factory($serverName, $messageSize)->createWhoisResponses(
            target: str_repeat('t', ServerLimits::MAX_NICKNAME_BYTES),
            requestedNickname: str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES),
            client: $client,
            channels: $channels,
        );
        $channelResponses = array_values(array_filter(
            $responses,
            static fn ($response): bool => $response->command === '319',
        ));

        $this->assertGreaterThan(1, count($channelResponses));

        foreach ($channelResponses as $response) {
            $this->assertTrue($messageSize->fits($response));
        }

        $actualChannelNames = [];

        foreach ($channelResponses as $response) {
            array_push($actualChannelNames, ...explode(' ', $response->parameter(2)));
        }

        $this->assertSame($expectedChannelNames, $actualChannelNames);
    }

    #[Test]
    public function it_creates_an_end_response_that_preserves_the_requested_nickname(): void
    {
        $response = $this->factory()->createEndOfWhoisResponse('Jane', 'jOhN');

        $this->assertSame('irc.test', $response->source);
        $this->assertSame('318', $response->command);
        $this->assertSame(['Jane', 'jOhN', 'End of /WHOIS list'], $response->parameters);
    }

    private function factory(
        ?ServerName $serverName = null,
        ?MessageSize $messageSize = null,
    ): WhoisResponseFactory {
        $serverName ??= new ServerName('irc.test');
        $messageSize ??= new MessageSize(new MessageEncoder());

        return new WhoisResponseFactory(
            config: new ServerConfig(
                serverName: $serverName,
                networkName: 'TestNet',
                listeners: [],
            ),
            responses: new NumericResponseFactory($serverName),
            messageSize: $messageSize,
            awayResponses: new AwayResponseFactory(
                responses: new NumericResponseFactory($serverName),
                messageText: new MessageTextLimiter(
                    $messageSize,
                    new ByteStringTruncator(),
                ),
            ),
        );
    }

    private function client(string $nickname): Client
    {
        $client = new Client('203.0.113.10');
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();

        return $client;
    }
}
