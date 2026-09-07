<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Registration;

use DateTimeImmutable;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\LusersResponseFactory;
use PhpIrc\Irc\Client\Registration\RegistrationWelcome;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class RegistrationWelcomeTest extends TestCase
{
    #[Test]
    public function it_sends_the_complete_registration_welcome_in_order(): void
    {
        $connection = new RecordingConnection();

        $this->welcome()->send($connection, 'John');

        $this->assertEquals(
            [
                $this->response('001', ['John', 'Welcome to the TestNet Network, John']),
                $this->response('002', ['John', 'Your host is irc.test, running version phpirc-test']),
                $this->response('003', ['John', 'This server was created 2026-08-29T10:15:30+01:00']),
                $this->response('004', ['John', 'irc.test', 'phpirc-test', '-', 'mntov']),
                $this->response(
                    '005',
                    [
                        'John',
                        'CASEMAPPING=ascii',
                        'CHANMODES=,,,mnt',
                        'CHANTYPES=#',
                        'CHANNELLEN=64',
                        'HOSTLEN=63',
                        'NICKLEN=30',
                        'NETWORK=TestNet',
                        'PREFIX=(ov)@+',
                        'TOPICLEN=307',
                        'USERLEN=18',
                        'are supported by this server',
                    ],
                ),
                $this->response('251', ['John', 'There are 0 users and 0 invisible on 1 servers']),
                $this->response('255', ['John', 'I have 0 clients and 0 servers']),
                $this->response('422', ['John', 'MOTD File is missing']),
            ],
            $connection->messages,
        );
    }

    #[Test]
    public function it_advertises_the_supported_channel_types(): void
    {
        $connection = new RecordingConnection();

        $this->welcome(new ChannelTypes('#&'))->send($connection, 'John');

        $this->assertContains('CHANTYPES=#&', $connection->messages[4]->parameters);
    }

    private function welcome(?ChannelTypes $channelTypes = null): RegistrationWelcome
    {
        $serverName = new ServerName('irc.test');
        $caseMapper = new AsciiCaseMapper();
        $responses = new NumericResponseFactory($serverName);
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return new RegistrationWelcome(
            new ServerConfig(
                serverName: $serverName,
                networkName: 'TestNet',
                listeners: [],
                softwareVersion: 'phpirc-test',
                startedAt: new DateTimeImmutable('2026-08-29T10:15:30+01:00'),
            ),
            $responses,
            $caseMapper,
            $channelTypes ?? new ChannelTypes(),
            new LusersResponseFactory($clients, $channels, $responses),
        );
    }

    /** @param list<string> $parameters */
    private function response(string $command, array $parameters): Message
    {
        return new Message(
            command: $command,
            parameters: $parameters,
            source: 'irc.test',
        );
    }
}
