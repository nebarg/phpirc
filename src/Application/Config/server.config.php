<?php

use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;

use function Tempest\env;

return new ServerConfig(
    serverName: new ServerName((string) env('IRC_SERVER_NAME', default: 'irc.local')),
    networkName: (string) env('IRC_NETWORK_NAME', default: 'PHPIRC'),
    listeners: [
        new ListenerConfig(
            address: (string) env('LISTEN_ADDRESS', default: '127.0.0.1'),
            port: (int) env('LISTEN_PORT', default: 6667),
        ),
    ],
    softwareVersion: (string) env('IRC_SERVER_VERSION', default: 'phpirc-0.1.0'),
);
