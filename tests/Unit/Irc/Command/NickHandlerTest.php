<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\NickHandler;
use PhpIrc\Irc\Command\NumericResponseSender;
use PhpIrc\Irc\Command\RegistrationCompleter;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Network\ClientRegistry;
use PhpIrc\Irc\Network\NicknameValidator;
use PhpIrc\Irc\Protocol\Message;
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
        $this->assertSame('NICK', $this->handler(new ClientRegistry())->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingNicknames')]
    public function it_rejects_a_missing_nickname(array $parameters): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = new ClientRegistry();

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
        $clients = new ClientRegistry();

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
        $clients = new ClientRegistry();
        $clients->claimNickname($owner, 'Grant');
        $connection = new RecordingConnection();

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['GRANT']),
        );

        $this->assertNull($client->nickname);
        $this->assertSame($owner, $clients->findByNickname('Grant'));
        $this->assertResponse(
            $connection,
            '433',
            ['*', 'GRANT', 'Nickname is already in use'],
        );
    }

    #[Test]
    public function it_claims_an_available_nickname(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = new ClientRegistry();

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['Grant']),
        );

        $this->assertSame('Grant', $client->nickname);
        $this->assertSame($client, $clients->findByNickname('Grant'));
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_completes_registration_when_the_other_details_are_present(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setUsername('grant');
        $client->setRealName('Grant Burrows');
        $clients = new ClientRegistry();

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['Grant']),
        );

        $this->assertTrue($client->registration->isComplete());
        $this->assertResponse(
            $connection,
            '001',
            ['Grant', 'Welcome to the TestNet Network, Grant'],
        );
    }

    #[Test]
    public function it_releases_a_previous_nickname_during_registration(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = new ClientRegistry();
        $clients->claimNickname($client, 'OldGrant');

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['NewGrant']),
        );

        $this->assertNull($clients->findByNickname('OldGrant'));
        $this->assertSame($client, $clients->findByNickname('NewGrant'));
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_acknowledges_a_registered_clients_nickname_change(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $clients = new ClientRegistry();
        $clients->claimNickname($client, 'OldGrant');
        $client->setUsername('grant');
        $client->setRealName('Grant Burrows');
        $client->completeRegistrationIfReady();

        $this->handler($clients)->handle(
            new CommandContext($connection, $client),
            $this->message(['NewGrant']),
        );

        $this->assertNull($clients->findByNickname('OldGrant'));
        $this->assertSame($client, $clients->findByNickname('NewGrant'));
        $this->assertCount(1, $connection->messages);
        $this->assertSame('OldGrant', $connection->messages[0]->source);
        $this->assertSame('NICK', $connection->messages[0]->command);
        $this->assertSame(['NewGrant'], $connection->messages[0]->parameters);
    }

    private function handler(ClientRegistry $clients): NickHandler
    {
        $serverName = new ServerName('irc.test');

        return new NickHandler(
            clients: $clients,
            nicknames: new NicknameValidator(),
            responses: new NumericResponseSender($serverName),
            registration: new RegistrationCompleter(
                new ServerConfig(
                    serverName: $serverName,
                    networkName: 'TestNet',
                    listeners: [],
                ),
            ),
        );
    }

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(
            tags: [],
            source: null,
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
}
