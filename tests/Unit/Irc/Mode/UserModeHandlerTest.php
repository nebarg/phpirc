<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Mode;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
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
    public function it_rejects_unsupported_user_mode_changes(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'MODE', parameters: ['John', '+i']),
        );

        $this->assertResponse($connection, '501', ['John', 'Unknown MODE flag']);
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
                numericResponses: $responses,
                errors: new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
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
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
