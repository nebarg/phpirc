<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Capability;

use DateTimeImmutable;
use PhpIrc\Irc\Client\Capability\Capability;
use PhpIrc\Irc\Client\Capability\ServerTimeMessageTagger;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageTag;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Time\ManualWallClock;
use Tests\TestCase;

final class ServerTimeMessageTaggerTest extends TestCase
{
    #[Test]
    public function it_does_not_tag_messages_for_a_client_without_the_capability(): void
    {
        $message = new Message(command: 'NOTICE', parameters: ['John', 'Hello']);

        $this->assertSame($message, $this->tagger()->tag($message, new Client()));
    }

    #[Test]
    public function it_removes_server_time_from_messages_for_a_client_without_the_capability(): void
    {
        $message = new Message(
            command: 'NOTICE',
            tags: [
                new MessageTag('example', 'value'),
                new MessageTag('time', '2020-01-02T03:04:05.006Z'),
            ],
        );

        $tagged = $this->tagger()->tag($message, new Client());

        $this->assertCount(1, $tagged->tags);
        $this->assertSame('example', $tagged->tags[0]->name);
    }

    #[Test]
    public function it_adds_a_utc_timestamp_with_millisecond_precision(): void
    {
        $client = new Client();
        $client->capabilities->enable(Capability::ServerTime);
        $message = new Message(
            command: 'NOTICE',
            parameters: ['John', 'Hello'],
            source: 'irc.test',
            tags: [new MessageTag('example', 'value')],
        );

        $tagged = $this->tagger()->tag($message, $client);

        $this->assertNotSame($message, $tagged);
        $this->assertSame($message->command, $tagged->command);
        $this->assertSame($message->parameters, $tagged->parameters);
        $this->assertSame($message->source, $tagged->source);
        $this->assertSame('example', $tagged->tags[0]->name);
        $this->assertSame('value', $tagged->tags[0]->value);
        $this->assertSame('time', $tagged->tags[1]->name);
        $this->assertSame('2026-09-12T13:34:56.789Z', $tagged->tags[1]->value);
    }

    #[Test]
    public function it_preserves_an_existing_server_time(): void
    {
        $client = new Client();
        $client->capabilities->enable(Capability::ServerTime);
        $message = new Message(
            command: 'NOTICE',
            tags: [new MessageTag('time', '2020-01-02T03:04:05.006Z')],
        );

        $this->assertSame($message, $this->tagger()->tag($message, $client));
    }

    private function tagger(): ServerTimeMessageTagger
    {
        return new ServerTimeMessageTagger(
            new ManualWallClock(new DateTimeImmutable('2026-09-12T14:34:56.789+01:00')),
        );
    }
}
