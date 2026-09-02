<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use LogicException;
use PhpIrc\Irc\Channel\ChannelRegistry;
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
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\LineBuffer;
use PhpIrc\Irc\Transport\MessageCodec;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\Support\Irc\Transport\RecordingConnection;
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
        $client = new Client();
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $handler = new class implements MessageHandler {
            public function handle(CommandContext $context, Message $message): void
            {
                throw new RuntimeException('Handler failed.');
            }
        };

        try {
            $this->connection(
                socket: $socket,
                handler: $handler,
                client: $client,
                clients: $clients,
            )->run();
            $this->fail('Expected the handler exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Handler failed.', $exception->getMessage());
            $this->assertSame(1, $socket->closeCalls);
            $this->assertNull($clients->connectionFor($client));
        }
    }

    #[Test]
    public function it_does_not_unregister_an_existing_client_when_registration_fails(): void
    {
        $socket = new FakeClientSocket();
        $client = new Client();
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $existingConnection = new RecordingConnection();
        $clients->register($client, $existingConnection);

        try {
            $this->connection(
                socket: $socket,
                handler: new RecordingMessageHandler(),
                client: $client,
                clients: $clients,
            )->run();
            $this->fail('Expected a logic exception.');
        } catch (LogicException $exception) {
            $this->assertSame('Client is already registered.', $exception->getMessage());
            $this->assertSame(1, $socket->closeCalls);
            $this->assertSame($existingConnection, $clients->connectionFor($client));
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
        $handler = new class($clients) implements MessageHandler {
            public function __construct(
                private readonly ClientRegistry $clients,
            ) {}

            public function handle(CommandContext $context, Message $message): void
            {
                $this->clients->claimNickname($context->client, 'John');
            }
        };

        $this->connection(
            socket: new FakeClientSocket(["NICK John\r\n"]),
            handler: $handler,
            client: $client,
            clients: $clients,
        )->run();

        $this->assertNull($clients->findByNickname('John'));
    }

    #[Test]
    public function it_registers_the_connection_while_handling_messages_and_unregisters_it_afterwards(): void
    {
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $handler = new class($clients) implements MessageHandler {
            public bool $connectionWasRegistered = false;

            public function __construct(
                private readonly ClientRegistry $clients,
            ) {}

            public function handle(CommandContext $context, Message $message): void
            {
                $this->connectionWasRegistered = $this->clients->connectionFor($context->client) === $context->connection;
            }
        };
        $client = new Client();

        $this->connection(
            socket: new FakeClientSocket(["PING :token\r\n"]),
            handler: $handler,
            client: $client,
            clients: $clients,
        )->run();

        $this->assertTrue($handler->connectionWasRegistered);
        $this->assertNull($clients->connectionFor($client));
    }

    #[Test]
    public function it_removes_the_client_from_channels_when_the_connection_ends(): void
    {
        $client = new Client();
        $channels = new ChannelRegistry(new AsciiCaseMapper());
        $channels->join('#php', $client);

        $this->connection(
            socket: new FakeClientSocket(),
            handler: new RecordingMessageHandler(),
            client: $client,
            channels: $channels,
        )->run();

        $this->assertNull($channels->find('#php'));
    }

    private function connection(
        ClientSocket $socket,
        MessageHandler $handler,
        ?Client $client = null,
        ?ClientRegistry $clients = null,
        ?ChannelRegistry $channels = null,
    ): ClientConnection {
        $caseMapper = new AsciiCaseMapper();

        return new ClientConnection(
            client: $client ?? new Client(),
            socket: $socket,
            codec: new MessageCodec(
                buffer: new LineBuffer(new ClientMessageSizeValidator()),
                parser: new MessageParser(),
                encoder: new MessageEncoder(),
            ),
            handler: $handler,
            lifecycle: new ClientConnectionLifecycle(
                clients: $clients ?? new ClientRegistry($caseMapper),
                channels: $channels ?? new ChannelRegistry($caseMapper),
            ),
        );
    }
}
