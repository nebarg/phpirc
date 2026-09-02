<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ChannelBroadcasterTest extends TestCase
{
    #[Test]
    public function it_sends_a_message_to_each_connected_channel_member(): void
    {
        $first = new Client();
        $second = new Client();
        $disconnected = new Client();
        $outsider = new Client();
        $channel = new Channel('#php');
        $channel->join($first);
        $channel->join($second);
        $channel->join($disconnected);
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $firstConnection = new RecordingConnection();
        $secondConnection = new RecordingConnection();
        $outsiderConnection = new RecordingConnection();
        $clients->register($first, $firstConnection);
        $clients->register($second, $secondConnection);
        $clients->register($outsider, $outsiderConnection);
        $message = new Message(command: 'JOIN', parameters: ['#php'], source: 'Jane');

        new ChannelBroadcaster($clients)->broadcast($channel, $message);

        $this->assertSame([$message], $firstConnection->messages);
        $this->assertSame([$message], $secondConnection->messages);
        $this->assertSame([], $outsiderConnection->messages);
    }

    #[Test]
    public function it_excludes_a_specific_member_from_the_broadcast(): void
    {
        $first = new Client();
        $second = new Client();
        $channel = new Channel('#php');
        $channel->join($first);
        $channel->join($second);
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $firstConnection = new RecordingConnection();
        $secondConnection = new RecordingConnection();
        $clients->register($first, $firstConnection);
        $clients->register($second, $secondConnection);
        $message = new Message(command: 'PRIVMSG', parameters: ['#php', 'Hello'], source: 'John');

        new ChannelBroadcaster($clients)->broadcastExcept($channel, $message, $first);

        $this->assertSame([], $firstConnection->messages);
        $this->assertSame([$message], $secondConnection->messages);
    }
}
