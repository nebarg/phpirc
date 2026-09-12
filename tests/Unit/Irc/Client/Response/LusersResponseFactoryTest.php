<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Response;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Response\LusersResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Transport\ConnectionStatistics;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class LusersResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_required_replies_when_the_server_is_empty(): void
    {
        [$factory] = $this->factory();

        $responses = $factory->createLusersResponses('John');

        $this->assertSame(
            ['251', '255', '265', '266', '250'],
            array_column($responses, 'command'),
        );
        $this->assertSame(
            ['John', 'There are 0 users and 0 invisible on 1 servers'],
            $responses[0]->parameters,
        );
        $this->assertSame(
            ['John', 'I have 0 clients and 0 servers'],
            $responses[1]->parameters,
        );
        $this->assertSame(
            ['John', '0', '0', 'Current local users 0, max 0'],
            $responses[2]->parameters,
        );
        $this->assertSame(
            ['John', '0', '0', 'Current global users 0, max 0'],
            $responses[3]->parameters,
        );
        $this->assertSame(
            ['John', 'Highest connection count: 0 (0 clients) (0 connections received)'],
            $responses[4]->parameters,
        );
    }

    #[Test]
    public function it_includes_registered_clients_unknown_connections_and_channels(): void
    {
        [$factory, $clients, $channels] = $this->factory();
        $john = $this->register($clients, 'John');
        $jane = $this->register($clients, 'Jane');
        $jane->enableMode(UserMode::Invisible);
        $this->register($clients, null);
        $channels->join('#one', $john);
        $channels->join('#ONE', $jane);
        $channels->join('#two', $jane);

        $responses = $factory->createLusersResponses('John');

        $this->assertSame(
            ['251', '253', '254', '255', '265', '266', '250'],
            array_column($responses, 'command'),
        );
        $this->assertSame(
            ['John', 'There are 1 users and 1 invisible on 1 servers'],
            $responses[0]->parameters,
        );
        $this->assertSame(['John', '1', 'unknown connection(s)'], $responses[1]->parameters);
        $this->assertSame(['John', '2', 'channels formed'], $responses[2]->parameters);
        $this->assertSame(
            ['John', 'I have 2 clients and 0 servers'],
            $responses[3]->parameters,
        );
        $this->assertSame(
            ['John', '2', '2', 'Current local users 2, max 2'],
            $responses[4]->parameters,
        );
        $this->assertSame(
            ['John', '2', '2', 'Current global users 2, max 2'],
            $responses[5]->parameters,
        );
        $this->assertSame(
            ['John', 'Highest connection count: 3 (2 clients) (3 connections received)'],
            $responses[6]->parameters,
        );
    }

    #[Test]
    public function it_preserves_peak_counts_after_clients_disconnect(): void
    {
        [$factory, $clients, , $statistics] = $this->factory();
        $john = $this->register($clients, 'John');
        $statistics->connectionAccepted();
        $statistics->clientRegistered();
        $jane = $this->register($clients, 'Jane');
        $statistics->connectionAccepted();
        $statistics->clientRegistered();
        $clients->unregister($jane);

        $responses = $factory->createLusersResponses('John');

        $this->assertSame(
            ['John', '1', '2', 'Current local users 1, max 2'],
            $responses[2]->parameters,
        );
        $this->assertSame(
            ['John', '1', '2', 'Current global users 1, max 2'],
            $responses[3]->parameters,
        );
        $this->assertSame(
            ['John', 'Highest connection count: 2 (2 clients) (2 connections received)'],
            $responses[4]->parameters,
        );
        $this->assertNotNull($clients->connectionFor($john));
    }

    /** @return array{LusersResponseFactory, ClientRegistry, ChannelRegistry, ConnectionStatistics} */
    private function factory(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $statistics = new ConnectionStatistics($clients);

        return [
            new LusersResponseFactory(
                clients: $clients,
                channels: $channels,
                responses: new NumericResponseFactory(new ServerName('irc.test')),
                statistics: $statistics,
            ),
            $clients,
            $channels,
            $statistics,
        ];
    }

    private function register(ClientRegistry $registry, ?string $nickname): Client
    {
        $client = new Client();
        $registry->register($client, new RecordingConnection());

        if ($nickname !== null) {
            $registry->claimNickname($client, $nickname);
            $client->setUsername(strtolower($nickname));
            $client->setRealName("{$nickname} Doe");
            $client->completeRegistrationIfReady();
        }

        return $client;
    }
}
