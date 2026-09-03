<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelTopicResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelTopicResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_no_topic_response(): void
    {
        $channel = new Channel('#php');

        $messages = $this->factory()->createResponses('John', $channel);

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

        $messages = $this->factory()->createResponses('John', $channel);

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

    private function factory(): ChannelTopicResponseFactory
    {
        return new ChannelTopicResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }
}
