<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Command;

use PhpIrc\Irc\Channel\ChannelListResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Command\ListHandler;
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

final class ListHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingChannels(): iterable
    {
        yield 'missing parameter' => [[]];
        yield 'empty parameter' => [['']];
    }

    #[Test]
    public function it_handles_the_list_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('LIST', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingChannels')]
    public function it_lists_every_channel_when_no_channel_is_given(array $parameters): void
    {
        [$handler, $channels] = $this->handler();
        $channels->join('#one', $this->client('John'));
        $channels->join('#ONE', $this->client('Jane'));
        $channels->join('#two', $this->client('Joe'));
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Outside')),
            new Message(command: 'LIST', parameters: $parameters),
        );

        $this->assertSame(
            ['321', '322', '322', '323'],
            array_map(
                static fn (Message $message): string => $message->command,
                $connection->messages,
            ),
        );
        $this->assertSame(['Outside', '#one', '2', ''], $connection->messages[1]->parameters);
        $this->assertSame(['Outside', '#two', '1', ''], $connection->messages[2]->parameters);
    }

    #[Test]
    public function it_lists_requested_existing_channels_case_insensitively(): void
    {
        [$handler, $channels] = $this->handler();
        $channels->join('#one', $this->client('John'));
        $channels->join('#two', $this->client('Jane'));
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('Outside')),
            new Message(command: 'LIST', parameters: ['#TWO,#missing,#ONE']),
        );

        $this->assertSame(
            ['321', '322', '322', '323'],
            array_map(
                static fn (Message $message): string => $message->command,
                $connection->messages,
            ),
        );
        $this->assertSame('#two', $connection->messages[1]->parameters[1]);
        $this->assertSame('#one', $connection->messages[2]->parameters[1]);
    }

    #[Test]
    public function it_returns_an_empty_list_when_requested_channels_do_not_exist(): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, $this->client('John')),
            new Message(command: 'LIST', parameters: ['#missing']),
        );

        $this->assertSame(
            ['321', '323'],
            array_map(
                static fn (Message $message): string => $message->command,
                $connection->messages,
            ),
        );
    }

    /** @return array{ListHandler, ChannelRegistry} */
    private function handler(): array
    {
        $channels = new ChannelRegistry(new AsciiCaseMapper());

        return [
            new ListHandler(
                channels: $channels,
                responses: new ChannelListResponseFactory(
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
}
