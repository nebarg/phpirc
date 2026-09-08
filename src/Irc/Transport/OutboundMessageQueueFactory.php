<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Transport\Task\BackgroundTaskRunner;
use Psr\Log\LoggerInterface;

final readonly class OutboundMessageQueueFactory
{
    public function __construct(
        private BackgroundTaskRunner $tasks,
        private ServerConfig $config,
        private LoggerInterface $logger,
    ) {}

    public function create(ClientSocket $socket): OutboundMessageQueue
    {
        return new OutboundMessageQueue(
            socket: $socket,
            tasks: $this->tasks,
            config: $this->config->outboundQueue,
            logger: $this->logger,
        );
    }
}
