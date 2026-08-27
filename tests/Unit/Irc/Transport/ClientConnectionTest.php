<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLong;
use PhpIrc\Irc\Protocol\InvalidMessage;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\ClientConnection;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\Connection;
use PhpIrc\Irc\Transport\LineBuffer;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;

final class ClientConnectionTest extends IntegrationTestCase
{
    #[Test]
    public function it_reads_parses_and_dispatches_a_complete_message(): void
    {
        $socket = new FakeClientSocket(["PRIVMSG #php :hello there\r\n"]);
        $handler = new RecordingMessageHandler;
        $connection = $this->connection($socket, $handler);

        $connection->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PRIVMSG', $handler->messages[0]->command);
        $this->assertSame(['#php', 'hello there'], $handler->messages[0]->parameters);
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
        $handler = new RecordingMessageHandler;

        $this->connection($socket, $handler)->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PRIVMSG', $handler->messages[0]->command);
        $this->assertSame(['#php', 'hello'], $handler->messages[0]->parameters);
    }

    #[Test]
    public function it_dispatches_multiple_messages_in_their_original_order(): void
    {
        $socket = new FakeClientSocket([
            "PING :one\r\nPONG :one\r\nNICK Grant\r\n",
        ]);
        $handler = new RecordingMessageHandler;

        $this->connection($socket, $handler)->run();

        $this->assertSame(
            ['PING', 'PONG', 'NICK'],
            array_map(
                static fn (Message $message): string => $message->command,
                $handler->messages,
            ),
        );
    }

    #[Test]
    public function it_stops_reading_and_closes_the_socket_at_eof(): void
    {
        $socket = new FakeClientSocket;
        $handler = new RecordingMessageHandler;

        $this->connection($socket, $handler)->run();

        $this->assertSame(1, $socket->readCalls);
        $this->assertSame(1, $socket->closeCalls);
        $this->assertSame([], $handler->messages);
    }

    #[Test]
    public function it_closes_the_socket_and_rethrows_invalid_messages(): void
    {
        $socket = new FakeClientSocket(["12\r\n"]);
        $handler = new RecordingMessageHandler;

        try {
            $this->connection($socket, $handler)->run();
            $this->fail('Expected an invalid message exception.');
        } catch (InvalidMessage) {
            $this->assertSame(1, $socket->closeCalls);
            $this->assertSame([], $handler->messages);
        }
    }

    #[Test]
    public function it_closes_the_socket_and_rethrows_overlong_input(): void
    {
        $socket = new FakeClientSocket([
            str_repeat('a', ClientMessageSizeValidator::MAX_MAIN_BYTES + 1) . "\r\n",
        ]);
        $handler = new RecordingMessageHandler;

        try {
            $this->connection($socket, $handler)->run();
            $this->fail('Expected an input-too-long exception.');
        } catch (InputTooLong) {
            $this->assertSame(1, $socket->closeCalls);
            $this->assertSame([], $handler->messages);
        }
    }

    #[Test]
    public function it_closes_the_socket_when_the_handler_throws(): void
    {
        $socket = new FakeClientSocket(["PING :token\r\n"]);
        $handler = new class implements MessageHandler {
            public function handle(Connection $connection, Message $message): void
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
        $socket = new FakeClientSocket;
        $handler = new RecordingMessageHandler;
        $connection = $this->connection($socket, $handler);

        $connection->send(new Message(
            tags: [],
            source: null,
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
        $socket = new FakeClientSocket;
        $handler = new RecordingMessageHandler;

        $this->connection($socket, $handler)->close();

        $this->assertSame(1, $socket->closeCalls);
    }

    private function connection(ClientSocket $socket, MessageHandler $handler): ClientConnection
    {
        return new ClientConnection(
            socket: $socket,
            buffer: new LineBuffer(new ClientMessageSizeValidator),
            parser: new MessageParser,
            encoder: new MessageEncoder,
            handler: $handler,
        );
    }
}
