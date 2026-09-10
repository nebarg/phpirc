<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Command\WhoisHandler;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Client\Response\WhoisResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class WhoisHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingNicknames(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_whois_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('WHOIS', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingNicknames')]
    public function it_requires_a_non_empty_nickname(array $parameters): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: $parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse($connection, '431', ['Jane', 'No nickname given']);
    }

    #[Test]
    public function it_returns_no_such_nickname_followed_by_the_end_response(): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: ['Missing']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            $connection,
            '401',
            ['Jane', 'Missing', 'No such nick/channel'],
        );
        $this->assertResponse(
            $connection,
            '318',
            ['Jane', 'Missing', 'End of /WHOIS list'],
            1,
        );
    }

    #[Test]
    public function it_does_not_return_a_client_that_has_not_completed_registration(): void
    {
        [$handler, $clients] = $this->handler();
        $unregistered = new Client('203.0.113.10');
        $clients->register($unregistered, new RecordingConnection());
        $clients->claimNickname($unregistered, 'John');
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: ['John']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            $connection,
            '401',
            ['Jane', 'John', 'No such nick/channel'],
        );
        $this->assertResponse(
            $connection,
            '318',
            ['Jane', 'John', 'End of /WHOIS list'],
            1,
        );
    }

    #[Test]
    public function it_returns_a_registered_client_case_insensitively(): void
    {
        [$handler, $clients] = $this->handler();
        $john = $this->client('John');
        $this->register($clients, $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: ['jOhN']),
        );

        $this->assertCount(3, $connection->messages);
        $this->assertResponse(
            $connection,
            '311',
            ['Jane', 'John', 'john', '203.0.113.10', '*', 'John Doe'],
        );
        $this->assertResponse(
            $connection,
            '312',
            ['Jane', 'John', 'irc.test', 'TestNet'],
            1,
        );
        $this->assertResponse(
            $connection,
            '318',
            ['Jane', 'jOhN', 'End of /WHOIS list'],
            2,
        );
    }

    #[Test]
    public function it_includes_the_clients_channels(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        $john = $this->client('John');
        $this->register($clients, $john);
        $channels->join('#PHP', $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: ['John']),
        );

        $this->assertCount(4, $connection->messages);
        $this->assertResponse(
            $connection,
            '319',
            ['Jane', 'John', '@#PHP'],
            2,
        );
        $this->assertResponse(
            $connection,
            '318',
            ['Jane', 'John', 'End of /WHOIS list'],
            3,
        );
    }

    #[Test]
    public function it_includes_the_clients_away_message(): void
    {
        [$handler, $clients] = $this->handler();
        $john = $this->client('John');
        $john->markAway('Gone for lunch');
        $this->register($clients, $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Jane')),
            new Message(command: 'WHOIS', parameters: ['John']),
        );

        $this->assertCount(4, $connection->messages);
        $this->assertResponse(
            $connection,
            '301',
            ['Jane', 'John', 'Gone for lunch'],
            2,
        );
    }

    /** @return array{WhoisHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $serverName = new ServerName('irc.test');
        $config = new ServerConfig(
            serverName: $serverName,
            networkName: 'TestNet',
            listeners: [],
        );
        $responses = new NumericResponseFactory($serverName);

        return [
            new WhoisHandler(
                clients: $clients,
                channels: $channels,
                whoisResponses: new WhoisResponseFactory(
                    config: $config,
                    responses: $responses,
                    messageSize: new MessageSize(new MessageEncoder()),
                    awayResponses: new AwayResponseFactory(
                        responses: $responses,
                        messageText: new MessageTextLimiter(
                            new MessageSize(new MessageEncoder()),
                            new ByteStringTruncator(),
                        ),
                    ),
                ),
                errors: new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
            ),
            $clients,
            $channels,
        ];
    }

    private function client(string $nickname): Client
    {
        $client = new Client('203.0.113.10');
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();

        return $client;
    }

    private function register(ClientRegistry $registry, Client $client): void
    {
        $nickname = $client->nickname;
        $this->assertNotNull($nickname);
        $registry->register($client, new RecordingConnection());
        $registry->claimNickname($client, $nickname);
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
        int $index = 0,
    ): void {
        $this->assertSame([], $connection->messages[$index]->tags);
        $this->assertSame('irc.test', $connection->messages[$index]->source);
        $this->assertSame($command, $connection->messages[$index]->command);
        $this->assertSame($parameters, $connection->messages[$index]->parameters);
    }
}
