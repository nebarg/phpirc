<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol\Target;

use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PhpIrc\Irc\Protocol\Target\TargetType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TargetClassifierTest extends TestCase
{
    /** @return iterable<string, array{string, TargetType}> */
    public static function targets(): iterable
    {
        yield 'channel' => ['#php', TargetType::Channel];
        yield 'invalid but channel-shaped target' => ['#', TargetType::Channel];
        yield 'nickname' => ['John', TargetType::Nickname];
        yield 'empty target' => ['', TargetType::Nickname];
    }

    #[Test]
    #[DataProvider('targets')]
    public function it_classifies_targets_by_their_syntax(
        string $target,
        TargetType $expected,
    ): void {
        $classifier = new TargetClassifier(new ChannelTypes());

        $this->assertSame($expected, $classifier->classify($target));
    }

    #[Test]
    public function it_uses_the_supported_channel_types(): void
    {
        $classifier = new TargetClassifier(new ChannelTypes('#&'));

        $this->assertSame(TargetType::Channel, $classifier->classify('&local'));
    }
}
