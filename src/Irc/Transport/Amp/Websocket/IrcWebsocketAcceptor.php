<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp\Websocket;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\WebsocketAcceptor;
use PhpIrc\Irc\Config\WebsocketConfig;
use PhpIrc\Irc\Transport\Websocket\IrcWebsocketProtocol;

use function Amp\Http\splitHeader;

final readonly class IrcWebsocketAcceptor implements WebsocketAcceptor
{
    public function __construct(
        private WebsocketConfig $config,
        private ErrorHandler $errors,
        private WebsocketAcceptor $handshake = new Rfc6455Acceptor(),
    ) {}

    public function handleHandshake(Request $request): Response
    {
        if ($request->getUri()->getPath() !== $this->config->path) {
            return $this->errors->handleError(HttpStatus::NOT_FOUND, request: $request);
        }

        if (! $this->allowsOrigin($request->getHeader('origin'))) {
            return $this->errors->handleError(HttpStatus::FORBIDDEN, request: $request);
        }

        $protocol = $this->negotiateProtocol($request);

        if ($protocol === null) {
            return $this->errors->handleError(
                HttpStatus::BAD_REQUEST,
                'A supported IRC WebSocket subprotocol is required.',
                $request,
            );
        }

        $response = $this->handshake->handleHandshake($request);

        if ($response->getStatus() === HttpStatus::SWITCHING_PROTOCOLS) {
            $response->setHeader('sec-websocket-protocol', $protocol->value);
        }

        return $response;
    }

    private function allowsOrigin(?string $origin): bool
    {
        if (in_array('*', $this->config->allowedOrigins, true)) {
            return true;
        }

        return $origin !== null && in_array($origin, $this->config->allowedOrigins, true);
    }

    private function negotiateProtocol(Request $request): ?IrcWebsocketProtocol
    {
        foreach (splitHeader($request, 'sec-websocket-protocol') ?? [] as $requestedProtocol) {
            $protocol = IrcWebsocketProtocol::tryFrom($requestedProtocol);

            if ($protocol !== null) {
                return $protocol;
            }
        }

        return null;
    }
}
