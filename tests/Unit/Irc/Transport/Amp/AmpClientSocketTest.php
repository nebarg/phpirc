<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use Amp\ByteStream\StreamException;
use Amp\CancelledException;
use Amp\Socket\InternetAddress;
use Amp\Socket\Socket as AmpSocket;
use Amp\Socket\TlsException;
use Amp\Socket\TlsState;
use Amp\TimeoutCancellation;
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
        $socket
            ->expects($this->once())
            ->method('getTlsState')
            ->willReturn(TlsState::Disabled);
        $socket
            ->expects($this->never())
            ->method('shutdownTls');
        $socket
            ->expects($this->once())
            ->method('getRemoteAddress')
            ->willReturn(new InternetAddress('203.0.113.10', 6697));

        $adapter = new AmpClientSocket($socket);

        $this->assertSame('203.0.113.10', $adapter->remoteAddress());
        $this->assertSame('incoming bytes', $adapter->read());
        $adapter->write('outgoing bytes');
        $adapter->close();
    }

    #[Test]
    public function it_shuts_down_tls_before_closing_the_amp_socket(): void
    {
        $tlsShutdown = false;
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('getTlsState')
            ->willReturn(TlsState::Enabled);
        $socket
            ->expects($this->once())
            ->method('shutdownTls')
            ->willReturnCallback(static function () use (&$tlsShutdown): void {
                $tlsShutdown = true;
            });
        $socket
            ->expects($this->once())
            ->method('close')
            ->willReturnCallback(static function () use (&$tlsShutdown): void {
                self::assertTrue($tlsShutdown);
            });

        new AmpClientSocket($socket)->close();
    }

    #[Test]
    public function it_closes_the_amp_socket_when_tls_shutdown_fails(): void
    {
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('getTlsState')
            ->willReturn(TlsState::Enabled);
        $socket
            ->expects($this->once())
            ->method('shutdownTls')
            ->willThrowException(new TlsException('TLS shutdown failed.'));
        $socket
            ->expects($this->once())
            ->method('close');

        new AmpClientSocket($socket)->close();
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

    #[Test]
    public function it_negotiates_tls_before_the_first_io_operation(): void
    {
        $tlsNegotiated = false;
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('setupTls')
            ->with($this->isInstanceOf(TimeoutCancellation::class))
            ->willReturnCallback(static function () use (&$tlsNegotiated): void {
                $tlsNegotiated = true;
            });
        $socket
            ->expects($this->once())
            ->method('read')
            ->willReturnCallback(static function () use (&$tlsNegotiated): string {
                self::assertTrue($tlsNegotiated);

                return 'incoming bytes';
            });
        $socket
            ->expects($this->once())
            ->method('write')
            ->with('outgoing bytes');
        $adapter = new AmpClientSocket($socket, tlsHandshakeTimeoutSeconds: 5);

        $this->assertSame('incoming bytes', $adapter->read());
        $adapter->write('outgoing bytes');
    }

    #[Test]
    public function it_translates_tls_negotiation_failures_to_a_client_socket_exception(): void
    {
        $cause = new TlsException('TLS negotiation failed.');
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('setupTls')
            ->willThrowException($cause);
        $socket->expects($this->once())->method('close');

        try {
            new AmpClientSocket($socket, tlsHandshakeTimeoutSeconds: 5)->read();
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to negotiate TLS with the client.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_translates_tls_handshake_timeouts_to_a_client_socket_exception(): void
    {
        $cause = new CancelledException();
        $socket = $this->createMock(AmpSocket::class);
        $socket
            ->expects($this->once())
            ->method('setupTls')
            ->willThrowException($cause);
        $socket->expects($this->once())->method('close');

        try {
            new AmpClientSocket($socket, tlsHandshakeTimeoutSeconds: 5)->read();
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to negotiate TLS with the client.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }
}
