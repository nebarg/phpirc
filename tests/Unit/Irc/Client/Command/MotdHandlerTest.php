<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Command\MotdHandler;
use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Client\Response\MotdResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class MotdHandlerTest extends TestCase
{
    #[Test]
    public function it_handles_the_motd_command(): void
    {
        $this->assertSame('MOTD', $this->handler()->command());
    }

    #[Test]
    public function it_sends_the_configured_motd(): void
    {
        $connection = new RecordingConnection();
        $client = new Client();
        $client->setNickname('John');

        $this->handler()->handle(
            new CommandContext($connection, $client),
            new Message(command: 'MOTD'),
        );

        $this->assertSame(['375', '372', '376'], array_column($connection->messages, 'command'));
        $this->assertSame(['John', '- Welcome to TestNet.'], $connection->messages[1]->parameters);
    }

    private function handler(): MotdHandler
    {
        $serverName = new ServerName('irc.test');

        return new MotdHandler(
            new MotdResponseFactory(
                $serverName,
                new Motd(['Welcome to TestNet.']),
                new NumericResponseFactory($serverName),
            ),
        );
    }
}
