<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Command;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Command\LusersHandler;
use PhpIrc\Irc\Client\Response\LusersResponseFactory;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class LusersHandlerTest extends TestCase
{
    #[Test]
    public function it_handles_the_lusers_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('LUSERS', $handler->command());
    }

    #[Test]
    public function it_sends_the_current_server_counts(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        $john = new Client();
        $connection = new RecordingConnection();
        $clients->register($john, $connection);
        $clients->claimNickname($john, 'John');
        $john->setUsername('john');
        $john->setRealName('John Doe');
        $john->completeRegistrationIfReady();
        $channels->join('#php', $john);

        $handler->handle(
            new CommandContext($connection, $john),
            new Message(command: 'LUSERS'),
        );

        $this->assertSame(['251', '254', '255'], array_column($connection->messages, 'command'));
        $this->assertSame(
            ['John', 'There are 1 users and 0 invisible on 1 servers'],
            $connection->messages[0]->parameters,
        );
        $this->assertSame(['John', '1', 'channels formed'], $connection->messages[1]->parameters);
        $this->assertSame(
            ['John', 'I have 1 clients and 0 servers'],
            $connection->messages[2]->parameters,
        );
    }

    /** @return array{LusersHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new LusersHandler(new LusersResponseFactory(
                clients: $clients,
                channels: $channels,
                responses: new NumericResponseFactory(new ServerName('irc.test')),
            )),
            $clients,
            $channels,
        ];
    }
}
