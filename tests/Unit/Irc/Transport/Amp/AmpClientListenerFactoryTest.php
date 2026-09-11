<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp;

use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket as AmpServerSocket;
use Amp\Socket\ServerSocketFactory;
use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ListenerTlsConfig;
use PhpIrc\Irc\Transport\Amp\AmpClientListener;
use PhpIrc\Irc\Transport\Amp\AmpClientListenerFactory;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class AmpClientListenerFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_plaintext_tcp_listener(): void
    {
        $server = $this->createStub(AmpServerSocket::class);
        $sockets = $this->createMock(ServerSocketFactory::class);
        $sockets
            ->expects($this->once())
            ->method('listen')
            ->with('tcp://127.0.0.1:6667', null)
            ->willReturn($server);

        $listener = new AmpClientListenerFactory($sockets)->create(
            new ListenerConfig('127.0.0.1', 6667),
        );

        $this->assertInstanceOf(AmpClientListener::class, $listener);
    }

    #[Test]
    public function it_configures_a_tls_certificate_and_private_key(): void
    {
        $server = $this->createStub(AmpServerSocket::class);
        $sockets = $this->createMock(ServerSocketFactory::class);
        $sockets
            ->expects($this->once())
            ->method('listen')
            ->with(
                'tcp://127.0.0.1:6697',
                $this->callback(function (?BindContext $context): bool {
                    $certificate = $context?->getTlsContext()?->getDefaultCertificate();

                    return $certificate?->getCertFile() === __FILE__ && $certificate?->getKeyFile() === __FILE__;
                }),
            )
            ->willReturn($server);

        $listener = new AmpClientListenerFactory($sockets)->create(
            new ListenerConfig(
                address: '127.0.0.1',
                port: 6697,
                tls: new ListenerTlsConfig(
                    certificateFile: __FILE__,
                    privateKeyFile: __FILE__,
                ),
            ),
        );

        $this->assertInstanceOf(AmpClientListener::class, $listener);
    }

    #[Test]
    public function it_rejects_an_unreadable_tls_certificate_before_listening(): void
    {
        $sockets = $this->createMock(ServerSocketFactory::class);
        $sockets->expects($this->never())->method('listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TLS certificate file is not readable: /missing/certificate.pem');

        new AmpClientListenerFactory($sockets)->create(
            new ListenerConfig(
                address: '127.0.0.1',
                port: 6697,
                tls: new ListenerTlsConfig(
                    certificateFile: '/missing/certificate.pem',
                    privateKeyFile: __FILE__,
                ),
            ),
        );
    }

    #[Test]
    public function it_rejects_an_unreadable_tls_private_key_before_listening(): void
    {
        $sockets = $this->createMock(ServerSocketFactory::class);
        $sockets->expects($this->never())->method('listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TLS private key file is not readable: /missing/private-key.pem');

        new AmpClientListenerFactory($sockets)->create(
            new ListenerConfig(
                address: '127.0.0.1',
                port: 6697,
                tls: new ListenerTlsConfig(
                    certificateFile: __FILE__,
                    privateKeyFile: '/missing/private-key.pem',
                ),
            ),
        );
    }
}
