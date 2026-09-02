<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Command\NickHandler;
use PhpIrc\Irc\Client\NicknameValidator;
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

final class NickHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingNicknames(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_nick_command(): void
    {
        $this->assertSame('NICK', $this->handler($this->registry())->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingNicknames')]
    public function it_rejects_a_missing_nickname(array $parameters): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($client, $connection);

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message($parameters),
        );

        $this->assertNull($client->nickname);
        $this->assertResponse(
            $connection,
            '431',
            ['*', 'No nickname given'],
        );
    }

    #[Test]
    public function it_rejects_an_invalid_nickname(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($client, $connection);

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['bad nickname']),
        );

        $this->assertNull($client->nickname);
        $this->assertResponse(
            $connection,
            '432',
            ['*', 'bad nickname', 'Erroneous nickname'],
        );
    }

    #[Test]
    public function it_rejects_a_nickname_claimed_by_another_client(): void
    {
        $owner = new Client();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($owner, new RecordingConnection());
        $clients->claimNickname($owner, 'John');
        $connection = new RecordingConnection();
        $clients->register($client, $connection);

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['JOHN']),
        );

        $this->assertNull($client->nickname);
        $this->assertSame($owner, $clients->findByNickname('John'));
        $this->assertResponse(
            $connection,
            '433',
            ['*', 'JOHN', 'Nickname is already in use'],
        );
    }

    #[Test]
    public function it_claims_an_available_nickname(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($client, $connection);

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['John']),
        );

        $this->assertSame('John', $client->nickname);
        $this->assertSame($client, $clients->findByNickname('John'));
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_completes_registration_when_the_other_details_are_present(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setUsername('john');
        $client->setRealName('John Doe');
        $clients = $this->registry();
        $clients->register($client, $connection);

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['John']),
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
    public function it_releases_a_previous_nickname_during_registration(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($client, $connection);
        $clients->claimNickname($client, 'OldJohn');

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['NewJohn']),
        );

        $this->assertNull($clients->findByNickname('OldJohn'));
        $this->assertSame($client, $clients->findByNickname('NewJohn'));
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_acknowledges_a_registered_clients_nickname_change(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = $this->registry();
        $clients->register($client, $connection);
        $clients->claimNickname($client, 'OldJohn');
        $client->setUsername('john');
        $client->setRealName('John Doe');
        $client->completeRegistrationIfReady();

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['NewJohn']),
        );

        $this->assertNull($clients->findByNickname('OldJohn'));
        $this->assertSame($client, $clients->findByNickname('NewJohn'));
        $this->assertCount(1, $connection->messages);
        $this->assertSame('OldJohn', $connection->messages[0]->source);
        $this->assertSame('NICK', $connection->messages[0]->command);
        $this->assertSame(['NewJohn'], $connection->messages[0]->parameters);
    }

    #[Test]
    public function it_broadcasts_a_registered_clients_nickname_change_once_to_shared_channel_peers(): void
    {
        $clients = $this->registry();
        $channels = new ChannelRegistry(new AsciiCaseMapper());
        $john = new Client();
        $johnConnection = new RecordingConnection();
        $clients->register($john, $johnConnection);
        $clients->claimNickname($john, 'OldJohn');
        $john->setUsername('john');
        $john->setRealName('John Doe');
        $john->completeRegistrationIfReady();
        $jane = new Client();
        $janeConnection = new RecordingConnection();
        $clients->register($jane, $janeConnection);
        $clients->claimNickname($jane, 'Jane');
        $outsider = new Client();
        $outsiderConnection = new RecordingConnection();
        $clients->register($outsider, $outsiderConnection);
        $clients->claimNickname($outsider, 'Outside');
        $channels->join('#one', $john);
        $channels->join('#one', $jane);
        $channels->join('#two', $john);
        $channels->join('#two', $jane);

        $this->handler($clients, $channels)->handle(
            new CommandContext($johnConnection, $john),
            $this->message(['NewJohn']),
        );

        $this->assertNicknameChange($johnConnection);
        $this->assertNicknameChange($janeConnection);
        $this->assertSame([], $outsiderConnection->messages);
    }

    private function handler(
        ClientRegistry $clients,
        ?ChannelRegistry $channels = null,
    ): NickHandler {
        $serverName = new ServerName('irc.test');
        $responses = new NumericResponseFactory($serverName);
        $channels ??= new ChannelRegistry(new AsciiCaseMapper());

        return new NickHandler(
            clients: $clients,
            nicknames: new NicknameValidator(),
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
            broadcaster: new ChannelBroadcaster($clients, $channels),
        );
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry(new AsciiCaseMapper());
    }

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(
            command: 'NICK',
            parameters: $parameters,
        );
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }

    private function assertNicknameChange(RecordingConnection $connection): void
    {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('OldJohn', $connection->messages[0]->source);
        $this->assertSame('NICK', $connection->messages[0]->command);
        $this->assertSame(['NewJohn'], $connection->messages[0]->parameters);
    }
}
