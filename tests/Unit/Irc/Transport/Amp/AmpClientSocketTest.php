<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use Amp\Socket\Socket as AmpSocket;
use PhpIrc\Irc\Transport\Amp\AmpClientSocket;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class AmpClientSocketTest extends IntegrationTestCase
{
    #[Test]
    public function it_delegates_raw_io_and_closure_to_the_amp_socket(): void
    {
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('read')
            ->willReturn('incoming bytes');
        $socket
            ->expects($this->once())
            ->method('write')
            ->with('outgoing bytes');
        $socket
            ->expects($this->once())
            ->method('close');

        $adapter = new AmpClientSocket($socket);

        $this->assertSame('incoming bytes', $adapter->read());
        $adapter->write('outgoing bytes');
        $adapter->close();
    }
}
