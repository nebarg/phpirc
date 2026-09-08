<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel\Response;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Response\ChannelNamesResponseFactory;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
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
        $channel->join($jane)->grant(MembershipMode::Voice);

        $messages = $this->factory()->createNamesResponses('Jane', $channel);

        $this->assertCount(2, $messages);
        $this->assertSame('irc.test', $messages[0]->source);
        $this->assertSame('353', $messages[0]->command);
        $this->assertSame(
            ['Jane', '=', '#php', '@John +Jane'],
            $messages[0]->parameters,
        );
        $this->assertSame('irc.test', $messages[1]->source);
        $this->assertSame('366', $messages[1]->command);
        $this->assertSame(
            ['Jane', '#php', 'End of /NAMES list'],
            $messages[1]->parameters,
        );
    }

    #[Test]
    public function it_creates_an_end_of_names_numeric_for_a_channel_name(): void
    {
        $message = $this->factory()->createEndOfNamesResponse('John', '#missing');

        $this->assertSame('irc.test', $message->source);
        $this->assertSame('366', $message->command);
        $this->assertSame(
            ['John', '#missing', 'End of /NAMES list'],
            $message->parameters,
        );
    }

    #[Test]
    public function it_splits_large_name_lists_into_messages_within_the_size_limit(): void
    {
        $channel = new Channel('#php');
        $expectedNames = [];

        for ($index = 1; $index <= 40; $index++) {
            $nickname = sprintf('Member%02d%s', $index, str_repeat('x', 20));
            $client = new Client();
            $client->setNickname($nickname);
            $membership = $channel->join($client);
            $expectedNames[] = $membership->highestPrefix() . $nickname;
        }

        $messages = $this->factory()->createNamesResponses('John', $channel);
        $endOfNames = array_pop($messages);
        $actualNames = [];
        $encoder = new MessageEncoder();

        $this->assertGreaterThan(1, count($messages));

        foreach ($messages as $message) {
            $this->assertSame('353', $message->command);
            $this->assertLessThanOrEqual(MessageSize::MAX_BYTES, strlen($encoder->encode($message)));
            array_push($actualNames, ...explode(' ', $message->parameters[3]));
        }

        $this->assertSame($expectedNames, $actualNames);
        $this->assertSame('366', $endOfNames?->command);
    }

    private function factory(): ChannelNamesResponseFactory
    {
        return new ChannelNamesResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
            new MessageSize(new MessageEncoder()),
        );
    }
}
