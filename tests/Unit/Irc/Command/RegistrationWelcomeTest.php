<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use DateTimeImmutable;
use PhpIrc\Irc\Command\NumericResponseFactory;
use PhpIrc\Irc\Command\RegistrationWelcome;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
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
                $this->response('004', ['John', 'irc.test', 'phpirc-test', '-', '-']),
                $this->response(
                    '005',
                    [
                        'John',
                        'CASEMAPPING=rfc1459',
                        'NICKLEN=30',
                        'NETWORK=TestNet',
                        'are supported by this server',
                    ],
                ),
                $this->response('422', ['John', 'MOTD File is missing']),
            ],
            $connection->messages,
        );
    }

    private function welcome(): RegistrationWelcome
    {
        $serverName = new ServerName('irc.test');

        return new RegistrationWelcome(
            new ServerConfig(
                serverName: $serverName,
                networkName: 'TestNet',
                listeners: [],
                softwareVersion: 'phpirc-test',
                startedAt: new DateTimeImmutable('2026-08-29T10:15:30+01:00'),
            ),
            new NumericResponseFactory($serverName),
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
