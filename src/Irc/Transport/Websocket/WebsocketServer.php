<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Websocket;

interface WebsocketServer
{
    public function start(): void;

    public function stop(): void;
}
