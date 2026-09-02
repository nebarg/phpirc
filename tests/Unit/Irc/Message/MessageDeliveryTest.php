<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Message;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class MessageDeliveryTest extends TestCase
{
    #[Test]
    public function it_delivers_to_a_client_using_their_canonical_nickname(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $unresolved = $delivery->deliver(
            sender: $john,
            command: 'NOTICE',
            targets: 'jAnE',
            text: 'Hello Jane',
        );

        $this->assertSame([], $unresolved);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'NOTICE',
            target: 'Jane',
            text: 'Hello Jane',
        );
    }

    #[Test]
    public function it_delivers_to_channel_members_except_the_sender(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#PHP', $john);
        $channels->join('#php', $jane);

        $unresolved = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#php',
            text: 'Hello channel',
        );

        $this->assertSame([], $unresolved);
        $this->assertSame([], $johnConnection->messages);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: '#PHP',
            text: 'Hello channel',
        );
    }

    #[Test]
    public function it_returns_unresolved_targets_after_delivering_valid_targets(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $unresolved = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: 'Missing,Jane,',
            text: 'Hello targets',
        );

        $this->assertSame(['Missing', ''], $unresolved);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: 'Jane',
            text: 'Hello targets',
        );
    }

    #[Test]
    public function it_preserves_irc_formatting_and_ctcp_bytes(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);
        $text = "\x01ACTION \x02waves\x02 in \x0304red\x0F\x01";

        $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: 'Jane',
            text: $text,
        );

        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: 'Jane',
            text: $text,
        );
    }

    /** @return array{MessageDelivery, ClientRegistry, ChannelRegistry} */
    private function delivery(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new MessageDelivery(
                clients: $clients,
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients, $channels),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(string $nickname, ClientRegistry $clients): array
    {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    private function assertDeliveredMessage(
        RecordingConnection $connection,
        string $command,
        string $target,
        string $text,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame('John', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame([$target, $text], $connection->messages[0]->parameters);
    }
}
