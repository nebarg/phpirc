<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServerNameTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_server_name(): void
    {
        $serverName = new ServerName('irc.example.test');

        $this->assertSame('irc.example.test', $serverName->value);
    }

    #[Test]
    public function it_accepts_a_server_name_at_the_length_limit(): void
    {
        $value = 'irc.' . str_repeat('a', ServerLimits::MAX_SERVER_NAME_BYTES - 4);

        $this->assertSame($value, new ServerName($value)->value);
    }

    #[Test]
    #[DataProvider('invalidServerNames')]
    public function it_rejects_an_invalid_server_name(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid server name.');

        new ServerName($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidServerNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['irc example.test'];
        yield 'colon' => ['irc:example.test'];
        yield 'null byte' => ["irc\0example.test"];
        yield 'carriage return' => ["irc\rexample.test"];
        yield 'line feed' => ["irc\nexample.test"];
        yield 'over maximum length' => [str_repeat('a', ServerLimits::MAX_SERVER_NAME_BYTES + 1)];
    }
}
