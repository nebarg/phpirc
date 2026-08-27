<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Tests\Support\Irc\Command\RecordingMessageHandler;
use Tests\Support\Irc\Transport\FakeClientSocket;

final class ClientConnectionFactoryTest extends IntegrationTestCase
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
        return new ClientConnectionFactory(
            validator: new ClientMessageSizeValidator(),
            parser: new MessageParser(),
            encoder: new MessageEncoder(),
            handler: $handler,
        );
    }
}
