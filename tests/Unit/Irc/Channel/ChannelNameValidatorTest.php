<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use PhpIrc\Irc\Channel\ChannelNameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelNameValidatorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validNames(): iterable
    {
        yield 'regular channel' => ['#php'];
        yield 'mixed case' => ['#PHP'];
        yield 'punctuation' => ['#php-help_testing'];
        yield 'maximum length' => ['#' . str_repeat('a', ChannelNameValidator::MAX_LENGTH - 1)];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'prefix only' => ['#'];
        yield 'missing prefix' => ['php'];
        yield 'unsupported local channel' => ['&php'];
        yield 'space' => ['#php help'];
        yield 'comma' => ['#php,help'];
        yield 'bell' => ["#php\x07help"];
        yield 'over maximum length' => ['#' . str_repeat('a', ChannelNameValidator::MAX_LENGTH)];
    }

    #[Test]
    #[DataProvider('validNames')]
    public function it_accepts_valid_channel_names(string $name): void
    {
        $this->assertTrue(new ChannelNameValidator()->isValid($name));
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_invalid_channel_names(string $name): void
    {
        $this->assertFalse(new ChannelNameValidator()->isValid($name));
    }
}
