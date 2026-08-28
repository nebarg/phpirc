<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Command\NumericResponseSender;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Network\Client;
use PhpIrc\Irc\Protocol\ResponseCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class NumericResponseSenderTest extends TestCase
{
    #[Test]
    public function it_sends_a_server_numeric_to_an_unregistered_client(): void
    {
        $connection = new RecordingConnection();

        new NumericResponseSender(new ServerName('irc.test'))->send(
            new CommandContext($connection, new Client()),
            ResponseCode::NoNicknameGiven,
            ['No nickname given'],
        );

        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame('431', $connection->messages[0]->command);
        $this->assertSame(
            ['*', 'No nickname given'],
            $connection->messages[0]->parameters,
        );
    }

    #[Test]
    public function it_uses_the_clients_nickname_as_the_numeric_target(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('Grant');

        new NumericResponseSender(new ServerName('irc.test'))->send(
            new CommandContext($connection, $client),
            ResponseCode::UnknownCommand,
            ['WHATEVER', 'Unknown command'],
        );

        $this->assertSame(
            ['Grant', 'WHATEVER', 'Unknown command'],
            $connection->messages[0]->parameters,
        );
    }
}
