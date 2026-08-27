<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use Amp\Socket\ServerSocket as AmpServerSocket;
use Amp\Socket\Socket as AmpSocket;
use PhpIrc\Irc\Transport\Amp\AmpClientListener;
use PhpIrc\Irc\Transport\Amp\AmpClientSocket;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AmpClientListenerTest extends TestCase
{
    #[Test]
    public function it_wraps_an_accepted_amp_socket(): void
    {
        $socket = $this->createStub(AmpSocket::class);
        $server = $this->createMock(AmpServerSocket::class);
        $server
            ->expects($this->once())
            ->method('accept')
            ->willReturn($socket);

        $accepted = new AmpClientListener($server)->accept();

        $this->assertInstanceOf(AmpClientSocket::class, $accepted);
    }

    #[Test]
    public function it_returns_null_when_the_amp_listener_closes(): void
    {
        $server = $this->createMock(AmpServerSocket::class);
        $server
            ->expects($this->once())
            ->method('accept')
            ->willReturn(null);

        $accepted = new AmpClientListener($server)->accept();

        $this->assertNull($accepted);
    }

    #[Test]
    public function it_delegates_closure_to_the_amp_listener(): void
    {
        $server = $this->createMock(AmpServerSocket::class);
        $server
            ->expects($this->once())
            ->method('close');

        new AmpClientListener($server)->close();
    }
}
