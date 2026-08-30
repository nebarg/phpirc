<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use DateTimeImmutable;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\NumericResponseFactory;
use PhpIrc\Irc\Command\RegistrationCompleter;
use PhpIrc\Irc\Command\RegistrationWelcome;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Network\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class RegistrationCompleterTest extends TestCase
{
    #[Test]
    public function it_does_nothing_until_the_client_has_all_registration_details(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');

        $this->completer()->completeIfReady(
            new CommandContext($connection, $client),
        );

        $this->assertFalse($client->registration->isComplete());
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_completes_registration_and_sends_the_welcome_response(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();

        $this->completer()->completeIfReady(
            new CommandContext($connection, $client),
        );

        $this->assertTrue($client->registration->isComplete());
        $this->assertCount(6, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('001', $connection->messages[0]->command);
        $this->assertSame(
            ['John', 'Welcome to the TestNet Network, John'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_sends_the_welcome_response_only_once(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();
        $context = new CommandContext($connection, $client);
        $completer = $this->completer();

        $completer->completeIfReady($context);
        $completer->completeIfReady($context);

        $this->assertCount(6, $connection->messages);
    }

    #[Test]
    public function it_waits_for_capability_negotiation_to_end(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();
        $client->registration->suspendForCapabilityNegotiation();
        $context = new CommandContext($connection, $client);
        $completer = $this->completer();

        $completer->completeIfReady($context);

        $this->assertFalse($client->registration->isComplete());
        $this->assertSame([], $connection->messages);

        $client->registration->resumeAfterCapabilityNegotiation();
        $completer->completeIfReady($context);

        $this->assertTrue($client->registration->isComplete());
        $this->assertCount(6, $connection->messages);
    }

    private function readyClient(): Client
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');

        return $client;
    }

    private function completer(): RegistrationCompleter
    {
        $serverName = new ServerName('irc.test');

        $responses = new NumericResponseFactory($serverName);

        return new RegistrationCompleter(
            new RegistrationWelcome(
                new ServerConfig(
                    serverName: $serverName,
                    networkName: 'TestNet',
                    listeners: [],
                    softwareVersion: 'phpirc-test',
                    startedAt: new DateTimeImmutable('2026-08-29T10:15:30+01:00'),
                ),
                $responses,
            ),
        );
    }
}
