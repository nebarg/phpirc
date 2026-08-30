<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLongException;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\ClientConnection;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\LineBuffer;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\TestCase;

final class ClientConnectionTest extends TestCase
{
    #[Test]
    public function it_reads_parses_and_dispatches_a_complete_message(): void
    {
        $socket = new FakeClientSocket(["PRIVMSG #php :hello there\r\n"]);
        $handler = new RecordingMessageHandler();
        $client = new Client();
        $connection = $this->connection($socket, $handler, $client);

        $connection->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PRIVMSG', $handler->messages[0]->command);
        $this->assertSame(['#php', 'hello there'], $handler->messages[0]->parameters);
        $this->assertSame($client, $handler->contexts[0]->client);
        $this->assertSame($connection, $handler->connections[0]);
    }

    #[Test]
    public function it_combines_a_message_split_across_socket_reads(): void
    {
        $socket = new FakeClientSocket([
            'PRIVMSG #php :hel',
            "lo\r",
            "\n",
        ]);
        $handler = new RecordingMessageHandler();

        $this->connection($socket, $handler)->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PRIVMSG', $handler->messages[0]->command);
        $this->assertSame(['#php', 'hello'], $handler->messages[0]->parameters);
    }

    #[Test]
    public function it_dispatches_multiple_messages_in_their_original_order(): void
    {
        $socket = new FakeClientSocket([
            "PING :one\r\nPONG :one\r\nNICK John\r\n",
        ]);
        $handler = new RecordingMessageHandler();

        $this->connection($socket, $handler)->run();

        $this->assertSame(
            ['PING', 'PONG', 'NICK'],
            array_map(
                static fn (Message $message): string => $message->command,
                $handler->messages,
            ),
        );
        $this->assertSame($handler->contexts[0], $handler->contexts[1]);
        $this->assertSame($handler->contexts[0], $handler->contexts[2]);
    }

    #[Test]
    public function it_stops_reading_and_closes_the_socket_at_eof(): void
    {
        $socket = new FakeClientSocket();
        $handler = new RecordingMessageHandler();

        $this->connection($socket, $handler)->run();

        $this->assertSame(1, $socket->readCalls);
        $this->assertSame(1, $socket->closeCalls);
        $this->assertSame([], $handler->messages);
    }

    #[Test]
    public function it_ignores_an_invalid_message_and_processes_the_next_message(): void
    {
        $socket = new FakeClientSocket(["PONG: hello\r\nPING :token\r\n"]);
        $handler = new RecordingMessageHandler();

        $this->connection($socket, $handler)->run();

        $this->assertSame(1, $socket->closeCalls);
        $this->assertCount(1, $handler->messages);
        $this->assertSame('PING', $handler->messages[0]->command);
        $this->assertSame(['token'], $handler->messages[0]->parameters);
    }

    #[Test]
    public function it_closes_the_socket_and_rethrows_overlong_input(): void
    {
        $socket = new FakeClientSocket([
            str_repeat('a', ClientMessageSizeValidator::MAX_MAIN_BYTES + 1) . "\r\n",
        ]);
        $handler = new RecordingMessageHandler();

        try {
            $this->connection($socket, $handler)->run();
            $this->fail('Expected an input-too-long exception.');
        } catch (InputTooLongException) {
            $this->assertSame(1, $socket->closeCalls);
            $this->assertSame([], $handler->messages);
        }
    }

    #[Test]
    public function it_closes_the_socket_when_the_handler_throws(): void
    {
        $socket = new FakeClientSocket(["PING :token\r\n"]);
        $handler = new class implements MessageHandler {
            public function handle(CommandContext $context, Message $message): void
            {
                throw new RuntimeException('Handler failed.');
            }
        };

        try {
            $this->connection($socket, $handler)->run();
            $this->fail('Expected the handler exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Handler failed.', $exception->getMessage());
            $this->assertSame(1, $socket->closeCalls);
        }
    }

    #[Test]
    public function it_encodes_messages_before_writing_them_to_the_socket(): void
    {
        $socket = new FakeClientSocket();
        $handler = new RecordingMessageHandler();
        $connection = $this->connection($socket, $handler);

        $connection->send(new Message(
            command: 'privmsg',
            parameters: ['#php', 'hello there'],
        ));

        $this->assertSame(
            ["PRIVMSG #php :hello there\r\n"],
            $socket->writes,
        );
    }

    #[Test]
    public function it_delegates_explicit_closure_to_the_socket(): void
    {
        $socket = new FakeClientSocket();
        $handler = new RecordingMessageHandler();

        $this->connection($socket, $handler)->close();

        $this->assertSame(1, $socket->closeCalls);
    }

    #[Test]
    public function it_releases_the_clients_nickname_when_the_connection_ends(): void
    {
        $client = new Client();
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $clients->claimNickname($client, 'John');

        $this->connection(
            socket: new FakeClientSocket(),
            handler: new RecordingMessageHandler(),
            client: $client,
            clients: $clients,
        )->run();

        $this->assertNull($clients->findByNickname('John'));
    }

    private function connection(
        ClientSocket $socket,
        MessageHandler $handler,
        ?Client $client = null,
        ?ClientRegistry $clients = null,
    ): ClientConnection {
        return new ClientConnection(
            client: $client ?? new Client(),
            clients: $clients ?? new ClientRegistry(new AsciiCaseMapper()),
            socket: $socket,
            buffer: new LineBuffer(new ClientMessageSizeValidator()),
            parser: new MessageParser(),
            encoder: new MessageEncoder(),
            handler: $handler,
        );
    }
}
