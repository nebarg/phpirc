<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\UserHandler;
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

final class UserHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function insufficientParameters(): iterable
    {
        yield 'none' => [[]];
        yield 'username only' => [['john']];
        yield 'two parameters' => [['john', '0']];
        yield 'three parameters' => [['john', '0', '*']];
        yield 'empty username' => [['', '0', '*', 'John Doe']];
    }

    #[Test]
    public function it_handles_the_user_command(): void
    {
        $this->assertSame('USER', $this->handler()->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('insufficientParameters')]
    public function it_rejects_missing_parameters(array $parameters): void
    {
        $connection = new RecordingConnection();
        $client = new Client();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message($parameters),
        );

        $this->assertNull($client->username);
        $this->assertNull($client->realName);
        $this->assertResponse(
            $connection,
            '461',
            ['*', 'USER', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_stores_the_username_and_real_name(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['john', '0', '*', 'John Doe']),
        );

        $this->assertSame('john', $client->username);
        $this->assertSame('John Doe', $client->realName);
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_ignores_the_legacy_mode_and_unused_parameters(): void
    {
        $client = new Client();

        $this->handler()->handle(
            new CommandContext(new RecordingConnection(), $client),
            $this->message(['john', '8', 'ignored.example', 'John Doe']),
        );

        $this->assertSame('john', $client->username);
        $this->assertSame('John Doe', $client->realName);
    }

    #[Test]
    public function it_completes_registration_when_a_nickname_is_present(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['john', '0', '*', 'John Doe']),
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
    public function it_rejects_a_second_user_command_before_registration_completes(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $handler = $this->handler();
        $context = new CommandContext($connection, $client);
        $handler->handle(
            $context,
            $this->message(['first', '0', '*', 'First Name']),
        );

        $handler->handle(
            $context,
            $this->message(['second', '0', '*', 'Second Name']),
        );

        $this->assertSame('first', $client->username);
        $this->assertSame('First Name', $client->realName);
        $this->assertResponse(
            $connection,
            '462',
            ['*', 'You may not reregister'],
        );
    }

    #[Test]
    public function it_rejects_user_after_registration(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('first');
        $client->setRealName('First Name');
        $client->completeRegistrationIfReady();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['second', '0', '*', 'Second Name']),
        );

        $this->assertSame('first', $client->username);
        $this->assertSame('First Name', $client->realName);
        $this->assertResponse(
            $connection,
            '462',
            ['John', 'You may not reregister'],
        );
    }

    #[Test]
    public function it_does_not_complete_while_capability_negotiation_is_active(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');
        $client->registration->suspendForCapabilityNegotiation();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['john', '0', '*', 'John Doe']),
        );

        $this->assertFalse($client->registration->isComplete());
        $this->assertSame([], $connection->messages);
    }

    private function handler(): UserHandler
    {
        $serverName = new ServerName('irc.test');
        $responses = new NumericResponseFactory($serverName);

        return new UserHandler(
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

    /** @param list<string> $parameters */
    private function message(array $parameters): Message
    {
        return new Message(command: 'USER', parameters: $parameters);
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
