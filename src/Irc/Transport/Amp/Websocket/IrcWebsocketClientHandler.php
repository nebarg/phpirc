<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp\Websocket;

use Amp\Http\Server\Middleware\Forwarded;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\WebsocketClient;
use LogicException;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\Websocket\IrcWebsocketProtocol;

final readonly class IrcWebsocketClientHandler implements WebsocketClientHandler
{
    public function __construct(
        private ClientConnectionSupervisor $connections,
    ) {}

    public function handleClient(
        WebsocketClient $client,
        Request $request,
        Response $response,
    ): void {
        $protocol = IrcWebsocketProtocol::tryFrom(
            $response->getHeader('sec-websocket-protocol') ?? '',
        ) ?? throw new LogicException('No IRC WebSocket subprotocol was negotiated.');

        $forwarded = $request->hasAttribute(Forwarded::class)
            ? $request->getAttribute(Forwarded::class)
            : null;

        $this->connections->run(new AmpWebsocketClientSocket(
            client: $client,
            protocol: $protocol,
            forwardedRemoteAddress: $forwarded instanceof Forwarded
                ? $forwarded->getFor()->getAddress()
                : null,
        ));
    }
}
