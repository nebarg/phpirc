<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientConnectionLifecycle;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;
use Tests\TestCase;

final class ClientConnectionFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_new_connection_for_each_socket(): void
    {
        $factory = $this->factory(new RecordingMessageHandler());

        $first = $factory->create(new FakeClientSocket());
        $second = $factory->create(new FakeClientSocket());

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_creates_a_new_client_for_each_connection(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory($handler);

        $factory->create(new FakeClientSocket(["PING :one\r\n"]))->run();
        $factory->create(new FakeClientSocket(["PING :two\r\n"]))->run();

        $this->assertCount(2, $handler->contexts);
        $this->assertNotSame(
            $handler->contexts[0]->client,
            $handler->contexts[1]->client,
        );
    }

    #[Test]
    public function it_does_not_share_buffered_input_between_connections(): void
    {
        $handler = new RecordingMessageHandler();
        $factory = $this->factory($handler);

        $factory->create(new FakeClientSocket([
            'PRIVMSG #php :unfinished',
        ]))->run();

        $factory->create(new FakeClientSocket([
            "PING :token\r\n",
        ]))->run();

        $this->assertCount(1, $handler->messages);
        $this->assertSame('PING', $handler->messages[0]->command);
        $this->assertSame(['token'], $handler->messages[0]->parameters);
    }

    private function factory(RecordingMessageHandler $handler): ClientConnectionFactory
    {
        $caseMapper = new AsciiCaseMapper();

        return new ClientConnectionFactory(
            validator: new ClientMessageSizeValidator(),
            parser: new MessageParser(),
            encoder: new MessageEncoder(),
            handler: $handler,
            lifecycle: new ClientConnectionLifecycle(
                clients: new ClientRegistry($caseMapper),
                channels: new ChannelRegistry($caseMapper),
            ),
        );
    }
}
