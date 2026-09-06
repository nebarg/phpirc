<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol\Target;

use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelTypesTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function defaultTargets(): iterable
    {
        yield 'regular channel' => ['#php', true];
        yield 'prefix without a name' => ['#', true];
        yield 'local channel' => ['&php', false];
        yield 'nickname' => ['John', false];
        yield 'empty target' => ['', false];
    }

    #[Test]
    #[DataProvider('defaultTargets')]
    public function it_recognises_targets_using_the_supported_prefixes(
        string $target,
        bool $expected,
    ): void {
        $this->assertSame($expected, new ChannelTypes()->isChannelTarget($target));
    }

    #[Test]
    public function it_can_recognise_additional_channel_types(): void
    {
        $channelTypes = new ChannelTypes('#&');

        $this->assertTrue($channelTypes->isChannelTarget('#global'));
        $this->assertTrue($channelTypes->isChannelTarget('&local'));
    }
}
