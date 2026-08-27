<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use Amp\ByteStream\StreamException;
use Amp\Socket\Socket as AmpSocket;
use PhpIrc\Irc\Transport\Amp\AmpClientSocket;
use PhpIrc\Irc\Transport\ClientSocketException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AmpClientSocketTest extends TestCase
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

    #[Test]
    public function it_translates_amp_read_failures_to_a_client_socket_exception(): void
    {
        $cause = new StreamException('Amp read failed.');
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('read')
            ->willThrowException($cause);

        try {
            new AmpClientSocket($socket)->read();
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to read from the client socket.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_translates_amp_write_failures_to_a_client_socket_exception(): void
    {
        $cause = new StreamException('Amp write failed.');
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('write')
            ->with('outgoing bytes')
            ->willThrowException($cause);

        try {
            new AmpClientSocket($socket)->write('outgoing bytes');
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to write to the client socket.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }
}
