<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp;

use PhpIrc\Irc\Transport\ClientConnectionFactory;
use PhpIrc\Irc\Transport\ClientListener;
use Psr\Log\LoggerInterface;
use Throwable;

use function Amp\async;

final readonly class IrcServer
{
    public function __construct(
        private ClientListener $listener,
        private ClientConnectionFactory $connections,
        private LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        try {
            while (($socket = $this->listener->accept()) !== null) {
                $connection = $this->connections->create($socket);

                async($connection->run(...))
                    ->catch(function (Throwable $exception): void {
                        $this->logger->error(
                            'IRC client connection failed.',
                            ['exception' => $exception],
                        );
                    })
                    ->ignore();
            }
        } finally {
            $this->listener->close();
        }
    }
}
