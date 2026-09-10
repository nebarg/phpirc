<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Command\WhoHandler;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Client\Policy\ClientVisibilityPolicy;
use PhpIrc\Irc\Client\Response\WhoResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class WhoHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingMasks(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedMasks(): iterable
    {
        yield 'unknown nickname' => ['Missing'];
        yield 'unknown channel' => ['#missing'];
        yield 'global mask' => ['0'];
        yield 'wildcard mask' => ['*'];
        yield 'nickname pattern' => ['Jo*'];
    }

    #[Test]
    public function it_handles_the_who_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('WHO', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingMasks')]
    public function it_requires_a_non_empty_mask(array $parameters): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('John')),
            new Message(command: 'WHO', parameters: $parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '461',
            parameters: ['John', 'WHO', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_returns_an_exact_registered_client_case_insensitively(): void
    {
        [$handler, $clients] = $this->handler();
        $john = $this->client('John');
        $this->register($clients, $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'WHO', parameters: ['jOhN']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['John', '*', 'john', '203.0.113.10', 'irc.test', 'John', 'H', '0 John Doe'],
        );
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['John', 'jOhN', 'End of WHO list'],
            index: 1,
        );
    }

    #[Test]
    public function it_marks_an_away_client_as_gone(): void
    {
        [$handler, $clients] = $this->handler();
        $john = $this->client('John');
        $john->markAway('Gone for lunch');
        $this->register($clients, $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'WHO', parameters: ['John']),
        );

        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['John', '*', 'john', '203.0.113.10', 'irc.test', 'John', 'G', '0 John Doe'],
        );
    }

    #[Test]
    public function it_lists_an_existing_channels_members(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        $john = $this->client('John');
        $jane = $this->client('Jane');
        $this->register($clients, $john);
        $this->register($clients, $jane);
        $channel = $channels->join('#PHP', $john);
        $channels->join('#php', $jane);
        $janeMembership = $channel->membershipFor($jane);
        $this->assertNotNull($janeMembership);
        $janeMembership->grant(MembershipMode::Voice);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $jane),
            new Message(command: 'WHO', parameters: ['#pHp']),
        );

        $this->assertCount(3, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['Jane', '#PHP', 'john', '203.0.113.10', 'irc.test', 'John', 'H@', '0 John Doe'],
        );
        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['Jane', '#PHP', 'jane', '203.0.113.10', 'irc.test', 'Jane', 'H+', '0 Jane Doe'],
            index: 1,
        );
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['Jane', '#pHp', 'End of WHO list'],
            index: 2,
        );
    }

    /**
     * @param string $mask
     */
    #[Test]
    #[DataProvider('unsupportedMasks')]
    public function it_returns_only_the_end_response_for_an_unsupported_or_unknown_mask(string $mask): void
    {
        [$handler, $clients] = $this->handler();
        $john = $this->client('John');
        $this->register($clients, $john);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'WHO', parameters: [$mask]),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['John', $mask, 'End of WHO list'],
        );
    }

    #[Test]
    public function it_does_not_return_a_client_that_has_not_completed_registration(): void
    {
        [$handler, $clients] = $this->handler();
        $requester = $this->client('Jane');
        $this->register($clients, $requester);
        $unregistered = new Client('203.0.113.11');
        $clients->register($unregistered, new RecordingConnection());
        $clients->claimNickname($unregistered, 'John');
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $requester),
            new Message(command: 'WHO', parameters: ['John']),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['Jane', 'John', 'End of WHO list'],
        );
    }

    #[Test]
    public function it_hides_an_invisible_client_without_a_shared_channel(): void
    {
        [$handler, $clients] = $this->handler();
        $requester = $this->client('Jane');
        $invisible = $this->client('John');
        $invisible->enableMode(UserMode::Invisible);
        $this->register($clients, $requester);
        $this->register($clients, $invisible);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $requester),
            new Message(command: 'WHO', parameters: ['John']),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['Jane', 'John', 'End of WHO list'],
        );
    }

    #[Test]
    public function it_returns_an_invisible_client_with_a_shared_channel(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        $requester = $this->client('Jane');
        $invisible = $this->client('John');
        $invisible->enableMode(UserMode::Invisible);
        $this->register($clients, $requester);
        $this->register($clients, $invisible);
        $channels->join('#php', $requester);
        $channels->join('#php', $invisible);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $requester),
            new Message(command: 'WHO', parameters: ['John']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['Jane', '*', 'john', '203.0.113.10', 'irc.test', 'John', 'H', '0 John Doe'],
        );
    }

    #[Test]
    public function a_channel_query_hides_invisible_members_from_outsiders(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        $requester = $this->client('Outside');
        $visible = $this->client('Jane');
        $invisible = $this->client('John');
        $invisible->enableMode(UserMode::Invisible);
        $this->register($clients, $requester);
        $this->register($clients, $visible);
        $this->register($clients, $invisible);
        $channels->join('#php', $visible);
        $channels->join('#php', $invisible);
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $requester),
            new Message(command: 'WHO', parameters: ['#php']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            connection: $connection,
            command: '352',
            parameters: ['Outside', '#php', 'jane', '203.0.113.10', 'irc.test', 'Jane', 'H@', '0 Jane Doe'],
        );
        $this->assertResponse(
            connection: $connection,
            command: '315',
            parameters: ['Outside', '#php', 'End of WHO list'],
            index: 1,
        );
    }

    /** @return array{WhoHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $serverName = new ServerName('irc.test');
        $responses = new NumericResponseFactory($serverName);

        return [
            new WhoHandler(
                clients: $clients,
                channels: $channels,
                visibility: new ClientVisibilityPolicy($channels),
                whoResponses: new WhoResponseFactory(
                    $serverName,
                    $responses,
                    new NumericErrorResponseFactory($responses, new ByteStringTruncator()),
                ),
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
