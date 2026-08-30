<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\CapHandler;
use PhpIrc\Irc\Client\Registration\RegistrationCompleter;
use PhpIrc\Irc\Client\Registration\RegistrationWelcome;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class CapHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>, string}> */
    public static function invalidSubcommands(): iterable
    {
        yield 'missing' => [[], '*'];
        yield 'unknown' => [['WAT'], 'WAT'];
    }

    #[Test]
    public function it_handles_the_cap_command(): void
    {
        $this->assertSame('CAP', $this->handler()->command());
    }

    #[Test]
    public function it_lists_an_empty_supported_capability_set_and_suspends_registration(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['ls', '302']),
        );

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertCapabilityReply($connection, ['John', 'LS', '']);
    }

    #[Test]
    public function it_lists_an_empty_enabled_capability_set(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['LIST']),
        );

        $this->assertCapabilityReply($connection, ['John', 'LIST', '']);
    }

    #[Test]
    public function it_rejects_all_requested_capabilities_and_suspends_registration(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['REQ', 'multi-prefix sasl']),
        );

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertCapabilityReply(
            $connection,
            ['John', 'NAK', 'multi-prefix sasl'],
        );
    }

    #[Test]
    public function it_resumes_and_completes_registration_on_cap_end(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();
        $client->registration->suspendForCapabilityNegotiation();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['END']),
        );

        $this->assertTrue($client->registration->isComplete());
        $this->assertCount(6, $connection->messages);
        $this->assertSame('001', $connection->messages[0]->command);
        $this->assertSame(
            ['John', 'Welcome to the TestNet Network, John'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_ignores_cap_end_after_registration(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();
        $client->completeRegistrationIfReady();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['END']),
        );

        $this->assertSame([], $connection->messages);
        $this->assertTrue($client->registration->isComplete());
    }

    #[Test]
    public function it_accepts_cap_ls_after_registration_without_reopening_registration(): void
    {
        $connection = new RecordingConnection();
        $client = $this->readyClient();
        $client->completeRegistrationIfReady();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['LS', '302']),
        );

        $this->assertTrue($client->registration->isComplete());
        $this->assertCapabilityReply($connection, ['John', 'LS', '']);
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('invalidSubcommands')]
    public function it_rejects_an_invalid_subcommand(
        array $parameters,
        string $invalidSubcommand,
    ): void {
        $connection = new RecordingConnection();

        $this->handler()->handle(
            new CommandContext($connection, new Client()),
            $this->message($parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame('410', $connection->messages[0]->command);
        $this->assertSame(
            ['*', $invalidSubcommand, 'Invalid CAP command'],
            $connection->messages[0]->parameters,
        );
    }

    private function handler(): CapHandler
    {
        $serverName = new ServerName('irc.test');
        $responses = new NumericResponseFactory($serverName);

        return new CapHandler(
            serverName: $serverName,
            responses: $responses,
            registration: new RegistrationCompleter(
                new RegistrationWelcome(
                    new ServerConfig(
                        serverName: $serverName,
                        networkName: 'TestNet',
                        listeners: [],
                    ),
                    $responses,
                    new AsciiCaseMapper(),
                ),
            ),
        );
    }

    private function readyClient(): Client
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');

        return $client;
    }

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(command: 'CAP', parameters: $parameters);
    }

    /** @param list<string> $parameters */
    private function assertCapabilityReply(
        RecordingConnection $connection,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('CAP', $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
