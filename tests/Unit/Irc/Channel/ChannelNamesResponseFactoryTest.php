<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelNamesResponseFactory;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelNamesResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_names_and_end_of_names_numerics(): void
    {
        $john = new Client();
        $john->setNickname('John');
        $jane = new Client();
        $jane->setNickname('Jane');
        $channel = new Channel('#php');
        $channel->join($john);
        $channel->join($jane);

        $messages = $this->factory()->createResponses('Jane', $channel);

        $this->assertCount(2, $messages);
        $this->assertSame('irc.test', $messages[0]->source);
        $this->assertSame('353', $messages[0]->command);
        $this->assertSame(
            ['Jane', '=', '#php', '@John Jane'],
            $messages[0]->parameters,
        );
        $this->assertSame('irc.test', $messages[1]->source);
        $this->assertSame('366', $messages[1]->command);
        $this->assertSame(
            ['Jane', '#php', 'End of /NAMES list'],
            $messages[1]->parameters,
        );
    }

    private function factory(): ChannelNamesResponseFactory
    {
        return new ChannelNamesResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }
}
