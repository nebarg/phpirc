<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
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

        new ChannelBroadcaster($clients, new ChannelRegistry(new AsciiCaseMapper()))
            ->broadcast($channel, $message);

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

        new ChannelBroadcaster($clients, new ChannelRegistry(new AsciiCaseMapper()))
            ->broadcastExcept($channel, $message, $first);

        $this->assertSame([], $firstConnection->messages);
        $this->assertSame([$message], $secondConnection->messages);
    }

    #[Test]
    public function it_sends_once_to_each_connected_peer_across_shared_channels(): void
    {
        $client = new Client();
        $peer = new Client();
        $otherPeer = new Client();
        $disconnectedPeer = new Client();
        $outsider = new Client();
        $clients = new ClientRegistry(new AsciiCaseMapper());
        $clientConnection = new RecordingConnection();
        $peerConnection = new RecordingConnection();
        $otherPeerConnection = new RecordingConnection();
        $outsiderConnection = new RecordingConnection();
        $clients->register($client, $clientConnection);
        $clients->register($peer, $peerConnection);
        $clients->register($otherPeer, $otherPeerConnection);
        $clients->register($outsider, $outsiderConnection);
        $channels = new ChannelRegistry(new AsciiCaseMapper());
        $channels->join('#one', $client);
        $channels->join('#one', $peer);
        $channels->join('#one', $disconnectedPeer);
        $channels->join('#two', $client);
        $channels->join('#two', $peer);
        $channels->join('#two', $otherPeer);
        $channels->join('#other', $outsider);
        $message = new Message(command: 'NICK', parameters: ['NewJohn'], source: 'John');

        new ChannelBroadcaster($clients, $channels)
            ->broadcastToSharedChannelPeers($client, $message);

        $this->assertSame([], $clientConnection->messages);
        $this->assertSame([$message], $peerConnection->messages);
        $this->assertSame([$message], $otherPeerConnection->messages);
        $this->assertSame([], $outsiderConnection->messages);
    }
}
