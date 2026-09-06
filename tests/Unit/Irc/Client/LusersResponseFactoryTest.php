<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\LusersResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class LusersResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_required_replies_when_the_server_is_empty(): void
    {
        [$factory] = $this->factory();

        $responses = $factory->createResponses('John');

        $this->assertSame(['251', '255'], array_column($responses, 'command'));
        $this->assertSame(
            ['John', 'There are 0 users and 0 invisible on 1 servers'],
            $responses[0]->parameters,
        );
        $this->assertSame(
            ['John', 'I have 0 clients and 0 servers'],
            $responses[1]->parameters,
        );
    }

    #[Test]
    public function it_includes_registered_clients_unknown_connections_and_channels(): void
    {
        [$factory, $clients, $channels] = $this->factory();
        $john = $this->register($clients, 'John');
        $jane = $this->register($clients, 'Jane');
        $this->register($clients, null);
        $channels->join('#one', $john);
        $channels->join('#ONE', $jane);
        $channels->join('#two', $jane);

        $responses = $factory->createResponses('John');

        $this->assertSame(['251', '253', '254', '255'], array_column($responses, 'command'));
        $this->assertSame(
            ['John', 'There are 2 users and 0 invisible on 1 servers'],
            $responses[0]->parameters,
        );
        $this->assertSame(['John', '1', 'unknown connection(s)'], $responses[1]->parameters);
        $this->assertSame(['John', '2', 'channels formed'], $responses[2]->parameters);
        $this->assertSame(
            ['John', 'I have 2 clients and 0 servers'],
            $responses[3]->parameters,
        );
    }

    /** @return array{LusersResponseFactory, ClientRegistry, ChannelRegistry} */
    private function factory(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new LusersResponseFactory(
                clients: $clients,
                channels: $channels,
                responses: new NumericResponseFactory(new ServerName('irc.test')),
            ),
            $clients,
            $channels,
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
