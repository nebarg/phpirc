<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\NumericResponseFactory;
use PhpIrc\Irc\Command\RegistrationCompleter;
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
        $client->setNickname('Grant');

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
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('001', $connection->messages[0]->command);
        $this->assertSame(
            ['Grant', 'Welcome to the TestNet Network, Grant'],
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

        $this->assertCount(1, $connection->messages);
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
        $this->assertCount(1, $connection->messages);
    }

    private function readyClient(): Client
    {
        $client = new Client();
        $client->setNickname('Grant');
        $client->setUsername('grant');
        $client->setRealName('Grant Burrows');

        return $client;
    }

    private function completer(): RegistrationCompleter
    {
        $serverName = new ServerName('irc.test');

        return new RegistrationCompleter(
            new ServerConfig(
                serverName: $serverName,
                networkName: 'TestNet',
                listeners: [],
            ),
            new NumericResponseFactory($serverName),
        );
    }
}
