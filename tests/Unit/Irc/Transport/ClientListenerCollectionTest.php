<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport;

use InvalidArgumentException;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\FakeClientListener;
use Tests\TestCase;

final class ClientListenerCollectionTest extends TestCase
{
    #[Test]
    public function it_returns_its_listeners(): void
    {
        $first = new FakeClientListener();
        $second = new FakeClientListener();
        $listeners = new ClientListenerCollection([$first, $second]);

        $this->assertSame([$first, $second], $listeners->all());
    }

    #[Test]
    public function it_requires_at_least_one_listener(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one client listener is required.');

        new ClientListenerCollection([]);
    }
}
