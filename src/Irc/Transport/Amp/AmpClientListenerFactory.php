<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\ServerTlsContext;
use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ListenerTlsConfig;
use RuntimeException;

final readonly class AmpClientListenerFactory
{
    public function __construct(
        private ServerSocketFactory $sockets = new ResourceServerSocketFactory(),
    ) {}

    public function create(ListenerConfig $config): AmpClientListener
    {
        return new AmpClientListener(
            server: $this->sockets->listen(
                'tcp://' . $config->address(),
                $this->createBindContext($config->tls),
            ),
            tlsHandshakeTimeoutSeconds: $config->tls?->handshakeTimeoutSeconds,
        );
    }

    private function createBindContext(?ListenerTlsConfig $tls): ?BindContext
    {
        if ($tls === null) {
            return null;
        }

        $this->requireReadableFile($tls->certificateFile, 'certificate');
        $this->requireReadableFile($tls->privateKeyFile, 'private key');

        $tlsContext = new ServerTlsContext()->withDefaultCertificate(
            new Certificate(
                certFile: $tls->certificateFile,
                keyFile: $tls->privateKeyFile,
            ),
        );

        return new BindContext()->withTlsContext($tlsContext);
    }

    private function requireReadableFile(string $path, string $description): void
    {
        if (is_file($path) && is_readable($path)) {
            return;
        }

        throw new RuntimeException("TLS {$description} file is not readable: {$path}");
    }
}
