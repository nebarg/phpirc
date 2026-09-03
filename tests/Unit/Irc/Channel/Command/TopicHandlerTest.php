<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
use PhpIrc\Irc\Channel\Command\TopicHandler;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class TopicHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingChannels(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_topic_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('TOPIC', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingChannels')]
    public function it_rejects_a_missing_channel(array $parameters): void
    {
        [$handler] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: $parameters),
        );

        $this->assertResponse(
            $connection,
            '461',
            ['John', 'TOPIC', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_rejects_an_unknown_channel(): void
    {
        [$handler] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: ['#missing']),
        );

        $this->assertResponse(
            $connection,
            '403',
            ['John', '#missing', 'No such channel'],
        );
    }

    #[Test]
    public function it_reports_when_a_channel_has_no_topic(): void
    {
        [$handler, $channels] = $this->handler();
        $channels->join('#php', $this->client('Jane'));
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: ['#PHP']),
        );

        $this->assertResponse(
            $connection,
            '331',
            ['John', '#php', 'No topic is set'],
        );
    }

    #[Test]
    public function it_returns_the_topic_and_metadata_case_insensitively(): void
    {
        [$handler, $channels] = $this->handler();
        $channel = $channels->join('#PHP', $this->client('Jane'));
        $channel->setTopic('PHP discussion', 'Jane');
        $topic = $channel->topic;
        $this->assertNotNull($topic);
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: ['#php']),
        );

        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            $connection,
            '332',
            ['John', '#PHP', 'PHP discussion'],
        );
        $this->assertResponse(
            $connection,
            '333',
            [
                'John',
                '#PHP',
                'Jane',
                (string) $topic->setAt->getTimestamp(),
            ],
            index: 1,
        );
    }

    #[Test]
    public function it_rejects_a_topic_change_from_a_non_member(): void
    {
        [$handler, $channels] = $this->handler();
        $channel = $channels->join('#php', $this->client('Jane'));
        $channel->setTopic('Original topic', 'Jane');
        $originalTopic = $channel->topic;
        [$client, $connection] = $this->connectedClient('John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: ['#PHP', 'Changed topic']),
        );

        $this->assertSame($originalTopic, $channel->topic);
        $this->assertResponse(
            $connection,
            '442',
            ['John', '#php', "You're not on that channel"],
        );
    }

    #[Test]
    public function it_sets_and_broadcasts_a_topic_to_every_channel_member(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John');
        [$jane, $janeConnection] = $this->connectedClient('Jane');
        $channel = $channels->join('#PHP', $john);
        $channels->join('#php', $jane);
        $clients->register($john, $johnConnection);
        $clients->register($jane, $janeConnection);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'TOPIC', parameters: ['#php', 'PHP discussion']),
        );

        $this->assertNotNull($channel->topic);
        $this->assertSame('PHP discussion', $channel->topic->text);
        $this->assertSame('John', $channel->topic->setBy);
        $this->assertCount(1, $johnConnection->messages);
        $this->assertSame($johnConnection->messages, $janeConnection->messages);
        $this->assertTopic($johnConnection, ['#PHP', 'PHP discussion']);
    }

    #[Test]
    public function it_clears_and_broadcasts_an_empty_topic(): void
    {
        [$handler, $channels, $clients] = $this->handler();
        [$client, $connection] = $this->connectedClient('John');
        $channel = $channels->join('#php', $client);
        $channel->setTopic('Old topic', 'Jane');
        $clients->register($client, $connection);

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'TOPIC', parameters: ['#php', '']),
        );

        $this->assertNull($channel->topic);
        $this->assertCount(1, $connection->messages);
        $this->assertTopic($connection, ['#php', '']);
    }

    /** @return array{TopicHandler, ChannelRegistry, ClientRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $channels = new ChannelRegistry($caseMapper);
        $clients = new ClientRegistry($caseMapper);
        $responses = new NumericResponseFactory(new ServerName('irc.test'));

        return [
            new TopicHandler(
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients, $channels),
                topicResponses: new ChannelTopicResponseFactory($responses),
                responses: $responses,
            ),
            $channels,
            $clients,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(string $nickname): array
    {
        return [$this->client($nickname), new RecordingConnection()];
    }

    private function client(string $nickname): Client
    {
        $client = new Client();
        $client->setNickname($nickname);

        return $client;
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

    /** @param list<string> $parameters */
    private function assertTopic(RecordingConnection $connection, array $parameters): void
    {
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('John', $connection->messages[0]->source);
        $this->assertSame('TOPIC', $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
