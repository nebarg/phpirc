<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelListResponseFactory;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelListResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_an_empty_channel_list(): void
    {
        $messages = $this->factory()->createResponses('John', []);

        $this->assertCount(2, $messages);
        $this->assertSame('irc.test', $messages[0]->source);
        $this->assertSame('321', $messages[0]->command);
        $this->assertSame(
            ['John', 'Channel', 'Users  Name'],
            $messages[0]->parameters,
        );
        $this->assertSame('irc.test', $messages[1]->source);
        $this->assertSame('323', $messages[1]->command);
        $this->assertSame(
            ['John', 'End of /LIST'],
            $messages[1]->parameters,
        );
    }

    #[Test]
    public function it_creates_a_list_entry_for_each_channel(): void
    {
        $first = new Channel('#PHP');
        $first->join(new Client());
        $first->join(new Client());
        $second = new Channel('#general');
        $second->join(new Client());

        $messages = $this->factory()->createResponses('John', [$first, $second]);

        $this->assertSame(
            ['321', '322', '322', '323'],
            array_map(static fn ($message): string => $message->command, $messages),
        );
        $this->assertSame(
            ['John', '#PHP', '2', ''],
            $messages[1]->parameters,
        );
        $this->assertSame(
            ['John', '#general', '1', ''],
            $messages[2]->parameters,
        );
    }

    private function factory(): ChannelListResponseFactory
    {
        return new ChannelListResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }
}
