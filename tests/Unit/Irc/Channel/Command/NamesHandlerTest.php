<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelNamesResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Command\NamesHandler;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class NamesHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingChannels(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    /** @return iterable<string, array{string}> */
    public static function unknownChannels(): iterable
    {
        yield 'missing channel' => ['#missing'];
        yield 'invalid channel name' => ['not-a-channel'];
    }

    #[Test]
    public function it_handles_the_names_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('NAMES', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingChannels')]
    public function it_ends_the_names_list_when_no_channel_is_given(array $parameters): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('John')),
            new Message(command: 'NAMES', parameters: $parameters),
        );

        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            $connection,
            '366',
            ['John', '*', 'End of /NAMES list'],
        );
    }

    #[Test]
    public function it_lists_an_existing_channels_members_case_insensitively(): void
    {
        [$handler, $channels] = $this->handler();
        $channel = $channels->join('#PHP', $this->client('Jane'));
        $channels->join('#php', $this->client('John'));
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Outside')),
            new Message(command: 'NAMES', parameters: ['#pHp']),
        );

        $this->assertSame($channel, $channels->find('#php'));
        $this->assertCount(2, $connection->messages);
        $this->assertResponse(
            $connection,
            '353',
            ['Outside', '=', '#PHP', '@Jane John'],
        );
        $this->assertResponse(
            $connection,
            '366',
            ['Outside', '#PHP', 'End of /NAMES list'],
            index: 1,
        );
    }

    #[Test]
    #[DataProvider('unknownChannels')]
    public function it_returns_only_end_of_names_for_an_unknown_channel(string $channelName): void
    {
        [$handler, $channels] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('John')),
            new Message(command: 'NAMES', parameters: [$channelName]),
        );

        $this->assertNull($channels->find($channelName));
        $this->assertCount(1, $connection->messages);
        $this->assertResponse(
            $connection,
            '366',
            ['John', $channelName, 'End of /NAMES list'],
        );
    }

    #[Test]
    public function it_processes_each_channel_in_a_comma_separated_list(): void
    {
        [$handler, $channels] = $this->handler();
        $channels->join('#one', $this->client('Jane'));
        $channels->join('#two', $this->client('John'));
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Outside')),
            new Message(command: 'NAMES', parameters: ['#ONE,#missing,#TWO']),
        );

        $this->assertSame(
            ['353', '366', '366', '353', '366'],
            array_map(
                static fn (Message $message): string => $message->command,
                $connection->messages,
            ),
        );
        $this->assertSame('#one', $connection->messages[0]->parameters[2]);
        $this->assertSame('#one', $connection->messages[1]->parameters[1]);
        $this->assertSame('#missing', $connection->messages[2]->parameters[1]);
        $this->assertSame('#two', $connection->messages[3]->parameters[2]);
        $this->assertSame('#two', $connection->messages[4]->parameters[1]);
    }

    /** @return array{NamesHandler, ChannelRegistry} */
    private function handler(): array
    {
        $channels = new ChannelRegistry(new AsciiCaseMapper());

        return [
            new NamesHandler(
                channels: $channels,
                responses: new ChannelNamesResponseFactory(
                    new NumericResponseFactory(new ServerName('irc.test')),
                ),
            ),
            $channels,
        ];
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
}
