<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\SharedChannelPeerBroadcaster;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class SharedChannelPeerBroadcasterTest extends TestCase
{
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

        new SharedChannelPeerBroadcaster($clients, $channels)
            ->broadcast($client, $message);

        $this->assertSame([], $clientConnection->messages);
        $this->assertSame([$message], $peerConnection->messages);
        $this->assertSame([$message], $otherPeerConnection->messages);
        $this->assertSame([], $outsiderConnection->messages);
    }
}
