<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Websocket;

final readonly class DisabledWebsocketServer implements WebsocketServer
{
    public function start(): void {}

    public function stop(): void {}
}
