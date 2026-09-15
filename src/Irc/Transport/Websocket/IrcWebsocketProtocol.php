<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Websocket;

enum IrcWebsocketProtocol: string
{
    case Text = 'text.ircv3.net';
    case Binary = 'binary.ircv3.net';
}
