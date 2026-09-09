<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Mode;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Mode\UserModeChanger;
use PhpIrc\Irc\Client\Response\UserModeResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Mode\UserModeHandler;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class UserModeHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function modeQueries(): iterable
    {
        yield 'missing modestring' => [['John']];
        yield 'empty modestring' => [['John', '']];
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('modeQueries')]
    public function it_returns_the_requesting_clients_current_modes(array $parameters): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: $parameters),
        );

        $this->assertResponse($connection, '221', ['John', '+']);
    }

    #[Test]
    public function it_finds_the_requesting_client_case_insensitively(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['jOhN']),
        );

        $this->assertResponse($connection, '221', ['John', '+']);
    }

    #[Test]
    public function it_returns_the_requesting_clients_enabled_modes(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $john->enableMode(UserMode::Invisible);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John']),
        );

        $this->assertResponse($connection, '221', ['John', '+i']);
    }

    #[Test]
    public function it_rejects_an_unknown_nickname(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['Missing']),
        );

        $this->assertResponse($connection, '401', ['John', 'Missing', 'No such nick/channel']);
    }

    #[Test]
    public function it_rejects_a_client_that_has_not_completed_registration(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $unregistered = new Client();
        $clients->register($unregistered, new RecordingConnection());
        $clients->claimNickname($unregistered, 'Jane');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['Jane']),
        );

        $this->assertResponse($connection, '401', ['John', 'Jane', 'No such nick/channel']);
    }

    #[Test]
    public function it_rejects_queries_for_another_client(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $this->register($clients, 'Jane');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['Jane']),
        );

        $this->assertResponse($connection, '502', ['John', 'Cannot change mode for other users']);
    }

    #[Test]
    public function it_applies_and_reports_an_invisible_mode_change(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '+i']),
        );

        $this->assertTrue($john->hasMode(UserMode::Invisible));
        $this->assertMessage($connection, 'John', 'MODE', ['John', '+i']);
    }

    #[Test]
    public function it_removes_and_reports_an_invisible_mode_change(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $john->enableMode(UserMode::Invisible);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '-i']),
        );

        $this->assertFalse($john->hasMode(UserMode::Invisible));
        $this->assertMessage($connection, 'John', 'MODE', ['John', '-i']);
    }

    #[Test]
    public function it_uses_the_clients_actual_nickname_in_a_mode_change(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['jOhN', '+i']),
        );

        $this->assertMessage($connection, 'John', 'MODE', ['John', '+i']);
    }

    #[Test]
    public function it_reports_unknown_modes_while_still_applying_supported_modes(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '+ix']),
        );

        $this->assertTrue($john->hasMode(UserMode::Invisible));
        $this->assertCount(2, $connection->messages);
        $this->assertMessage(
            $connection,
            'irc.test',
            '501',
            ['John', 'Unknown MODE flag'],
        );
        $this->assertMessage(
            $connection,
            'John',
            'MODE',
            ['John', '+i'],
            index: 1,
        );
    }

    #[Test]
    public function it_does_not_report_a_mode_that_was_already_enabled(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');
        $john->enableMode(UserMode::Invisible);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '+i']),
        );

        $this->assertSame([], $connection->messages);
    }

    #[Test]
    public function it_ignores_a_modestring_containing_only_action_signs(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '+-']),
        );

        $this->assertSame([], $connection->messages);
    }

    /** @return array{UserModeHandler, ClientRegistry} */
    private function handler(): array
    {
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $responses = new NumericResponseFactory(new ServerName('irc.test'));

        return [
            new UserModeHandler(
                clients: $clients,
                modeChanger: new UserModeChanger(),
                modeResponses: new UserModeResponseFactory(
                    $responses,
                    new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
                ),
            ),
            $clients,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function register(ClientRegistry $registry, string $nickname): array
    {
        $client = new Client();
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();
        $connection = new RecordingConnection();
        $registry->register($client, $connection);
        $registry->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertMessage($connection, 'irc.test', $command, $parameters);
    }

    /** @param list<string> $parameters */
    private function assertMessage(
        RecordingConnection $connection,
        string $source,
        string $command,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame($source, $connection->messages[$index]->source);
        $this->assertSame($command, $connection->messages[$index]->command);
        $this->assertSame($parameters, $connection->messages[$index]->parameters);
    }
}
