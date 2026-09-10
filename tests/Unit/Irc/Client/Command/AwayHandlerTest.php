<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\AwayHandler;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class AwayHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function presentParameters(): iterable
    {
        yield 'missing reason' => [[]];
        yield 'empty reason' => [['']];
    }

    #[Test]
    public function it_handles_the_away_command(): void
    {
        $this->assertSame('AWAY', $this->handler()->command());
    }

    #[Test]
    public function it_marks_the_client_as_away(): void
    {
        $client = $this->client();
        $connection = new RecordingConnection();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            new Message(command: 'AWAY', parameters: ['Gone for lunch']),
        );

        $this->assertTrue($client->isAway());
        $this->assertSame('Gone for lunch', $client->awayMessage);
        $this->assertResponse(
            $connection,
            '306',
            ['John', 'You have been marked as being away'],
        );
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('presentParameters')]
    public function it_marks_the_client_as_present(array $parameters): void
    {
        $client = $this->client();
        $client->markAway('Gone for lunch');
        $connection = new RecordingConnection();

        $this->handler()->handle(
            new CommandContext($connection, $client),
            new Message(command: 'AWAY', parameters: $parameters),
        );

        $this->assertFalse($client->isAway());
        $this->assertNull($client->awayMessage);
        $this->assertResponse(
            $connection,
            '305',
            ['John', 'You are no longer marked as being away'],
        );
    }

    #[Test]
    public function it_preserves_a_whitespace_away_message(): void
    {
        $client = $this->client();

        $this->handler()->handle(
            new CommandContext(new RecordingConnection(), $client),
            new Message(command: 'AWAY', parameters: [' ']),
        );

        $this->assertSame(' ', $client->awayMessage);
    }

    #[Test]
    public function it_truncates_the_away_message_to_the_advertised_limit(): void
    {
        $client = $this->client();
        $awayMessageBytes = max(0, ServerLimits::MAX_AWAY_MESSAGE_BYTES);

        $this->handler()->handle(
            new CommandContext(new RecordingConnection(), $client),
            new Message(
                command: 'AWAY',
                parameters: [str_repeat('a', $awayMessageBytes + 1)],
            ),
        );

        $this->assertSame(
            str_repeat('a', $awayMessageBytes),
            $client->awayMessage,
        );
    }

    private function handler(): AwayHandler
    {
        $strings = new ByteStringTruncator();
        $responses = new NumericResponseFactory(new ServerName('irc.test'));

        return new AwayHandler(
            limits: new ServerLimits($strings),
            awayResponses: new AwayResponseFactory(
                responses: $responses,
                messageText: new MessageTextLimiter(
                    new MessageSize(new MessageEncoder()),
                    $strings,
                ),
            ),
        );
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setNickname('John');

        return $client;
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
