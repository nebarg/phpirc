<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Config;

use InvalidArgumentException;
use PhpIrc\Irc\Config\ListenerTlsConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListenerTlsConfigTest extends TestCase
{
    #[Test]
    public function it_accepts_certificate_key_and_timeout_settings(): void
    {
        $tls = new ListenerTlsConfig(
            certificateFile: '/certificates/fullchain.pem',
            privateKeyFile: '/certificates/private-key.pem',
            handshakeTimeoutSeconds: 5,
        );

        $this->assertSame('/certificates/fullchain.pem', $tls->certificateFile);
        $this->assertSame('/certificates/private-key.pem', $tls->privateKeyFile);
        $this->assertSame(5, $tls->handshakeTimeoutSeconds);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidCertificateFiles(): iterable
    {
        yield 'missing certificate' => ['', '/certificates/private-key.pem', 'TLS certificate file cannot be empty.'];
        yield 'missing private key' => ['/certificates/fullchain.pem', '', 'TLS private key file cannot be empty.'];
    }

    #[Test]
    #[DataProvider('invalidCertificateFiles')]
    public function it_rejects_empty_certificate_files(
        string $certificateFile,
        string $privateKeyFile,
        string $expectedMessage,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new ListenerTlsConfig($certificateFile, $privateKeyFile);
    }

    #[Test]
    public function it_rejects_a_non_positive_handshake_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TLS handshake timeout must be at least one second.');

        new ListenerTlsConfig('/certificate.pem', '/private-key.pem', 0);
    }
}
