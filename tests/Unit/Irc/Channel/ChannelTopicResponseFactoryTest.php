<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelTopicResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_no_topic_response(): void
    {
        $channel = new Channel('#php');

        $messages = $this->factory()->createCurrentTopicResponses('John', $channel);

        $this->assertCount(1, $messages);
        $this->assertSame('irc.test', $messages[0]->source);
        $this->assertSame('331', $messages[0]->command);
        $this->assertSame(
            ['John', '#php', 'No topic is set'],
            $messages[0]->parameters,
        );
    }

    #[Test]
    public function it_creates_topic_and_topic_metadata_responses(): void
    {
        $channel = new Channel('#PHP');
        $channel->setTopic('PHP discussion', 'Jane');
        $topic = $channel->topic;
        $this->assertNotNull($topic);

        $messages = $this->factory()->createCurrentTopicResponses('John', $channel);

        $this->assertCount(2, $messages);
        $this->assertSame('irc.test', $messages[0]->source);
        $this->assertSame('332', $messages[0]->command);
        $this->assertSame(
            ['John', '#PHP', 'PHP discussion'],
            $messages[0]->parameters,
        );
        $this->assertSame('irc.test', $messages[1]->source);
        $this->assertSame('333', $messages[1]->command);
        $this->assertSame(
            [
                'John',
                '#PHP',
                'Jane',
                (string) $topic->setAt->getTimestamp(),
            ],
            $messages[1]->parameters,
        );
    }

    #[Test]
    public function it_omits_no_topic_when_only_an_existing_topic_should_be_returned(): void
    {
        $messages = $this->factory()->createExistingTopicResponses(
            'John',
            new Channel('#php'),
        );

        $this->assertSame([], $messages);
    }

    #[Test]
    public function it_keeps_topic_responses_within_the_message_size_limit(): void
    {
        $serverName = new ServerName('irc.' . str_repeat('s', 59));
        $channel = new Channel('#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1));
        $channel->setTopic(
            str_repeat('t', ServerLimits::MAX_TOPIC_BYTES),
            str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES),
        );

        $messages = $this->factory($serverName)->createCurrentTopicResponses(
            str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES),
            $channel,
        );
        $messageSize = new MessageSize(new MessageEncoder());

        foreach ($messages as $message) {
            $this->assertTrue($messageSize->fits($message));
        }
    }

    private function factory(?ServerName $serverName = null): ChannelTopicResponseFactory
    {
        return new ChannelTopicResponseFactory(
            new NumericResponseFactory($serverName ?? new ServerName('irc.test')),
        );
    }
}
