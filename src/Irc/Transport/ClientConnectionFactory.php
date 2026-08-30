<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\MessageHandler;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageParser;

final readonly class ClientConnectionFactory
{
    public function __construct(
        private ClientMessageSizeValidator $validator,
        private MessageParser $parser,
        private MessageEncoder $encoder,
        private MessageHandler $handler,
        private ClientRegistry $clients,
    ) {}

    public function create(ClientSocket $socket): ClientConnection
    {
        return new ClientConnection(
            client: new Client(),
            clients: $this->clients,
            socket: $socket,
            buffer: new LineBuffer($this->validator),
            parser: $this->parser,
            encoder: $this->encoder,
            handler: $this->handler,
        );
    }
}
