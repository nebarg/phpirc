<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\NumericResponseFactory;
use PhpIrc\Irc\Command\RegistrationCompleter;
use PhpIrc\Irc\Command\UserHandler;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Protocol\Message;
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
        yield 'username only' => [['grant']];
        yield 'two parameters' => [['grant', '0']];
        yield 'three parameters' => [['grant', '0', '*']];
        yield 'empty username' => [['', '0', '*', 'Grant Burrows']];
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
            $this->message(['grant', '0', '*', 'Grant Burrows']),
        );

        $this->assertSame('grant', $client->username);
        $this->assertSame('Grant Burrows', $client->realName);
        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_ignores_the_legacy_mode_and_unused_parameters(): void
    {
        $client = new Client();

        $this->handler()->handle(
            new CommandContext(new RecordingConnection(), $client),
            $this->message(['grant', '8', 'ignored.example', 'Grant Burrows']),
        );

        $this->assertSame('grant', $client->username);
        $this->assertSame('Grant Burrows', $client->realName);
    }

    #[Test]
    public function it_completes_registration_when_a_nickname_is_present(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('Grant');

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['grant', '0', '*', 'Grant Burrows']),
        );

        $this->assertTrue($client->registration->isComplete());
        $this->assertResponse(
            $connection,
            '001',
            ['Grant', 'Welcome to the TestNet Network, Grant'],
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
        $client->setNickname('Grant');
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
            ['Grant', 'You may not reregister'],
        );
    }

    #[Test]
    public function it_does_not_complete_while_capability_negotiation_is_active(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('Grant');
        $client->registration->suspendForCapabilityNegotiation();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            $this->message(['grant', '0', '*', 'Grant Burrows']),
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
                new ServerConfig(
                    serverName: $serverName,
                    networkName: 'TestNet',
                    listeners: [],
                ),
                $responses,
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
