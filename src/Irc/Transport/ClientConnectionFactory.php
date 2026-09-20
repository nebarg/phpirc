<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Capability\ServerTimeMessageTagger;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\HostCloak;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\MessageParser;
use PhpIrc\Irc\Transport\Flood\FloodProtectionFactory;
use PhpIrc\Irc\Transport\Keepalive\ConnectionKeepaliveFactory;

final readonly class ClientConnectionFactory
{
    public function __construct(
        private ClientMessageSizeValidator $validator,
        private MessageParser $parser,
        private OutboundMessagePreparer $outboundMessages,
        private MessageHandler $handler,
        private ClientConnectionLifecycle $lifecycle,
        private ConnectionKeepaliveFactory $keepalives,
        private FloodProtectionFactory $floodProtection,
        private ServerLimits $limits,
        private OutboundMessageQueueFactory $outboundQueues,
        private ServerTimeMessageTagger $serverTime,
        private HostCloak $hostCloak,
    ) {}

    public function create(ClientSocket $socket): ClientConnection
    {
        $hostname = $this->limits->truncateHostname($socket->remoteAddress());

        return new ClientConnection(
            client: new Client(
                hostname: $hostname,
                publicHostname: $this->hostCloak->mask($hostname),
            ),
            socket: $socket,
            codec: new MessageCodec(
                buffer: new LineBuffer($this->validator),
                parser: $this->parser,
                outboundMessages: $this->outboundMessages,
            ),
            handler: $this->floodProtection->protect($this->handler),
            lifecycle: $this->lifecycle,
            keepalive: $this->keepalives->create(),
            outboundQueue: $this->outboundQueues->create($socket),
            serverTime: $this->serverTime,
        );
    }
}
