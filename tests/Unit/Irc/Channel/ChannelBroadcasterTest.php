<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Transport\ClientConnectionRegistry;
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
        $connections = new ClientConnectionRegistry();
        $firstConnection = new RecordingConnection();
        $secondConnection = new RecordingConnection();
        $outsiderConnection = new RecordingConnection();
        $connections->register($first, $firstConnection);
        $connections->register($second, $secondConnection);
        $connections->register($outsider, $outsiderConnection);
        $message = new Message(command: 'JOIN', parameters: ['#php'], source: 'Jane');

        new ChannelBroadcaster($connections)->broadcast($channel, $message);

        $this->assertSame([$message], $firstConnection->messages);
        $this->assertSame([$message], $secondConnection->messages);
        $this->assertSame([], $outsiderConnection->messages);
    }
}
