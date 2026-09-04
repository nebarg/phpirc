<?php

use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Config\KeepaliveConfig;
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
    keepalive: new KeepaliveConfig(
        pingIntervalSeconds: (int) env('IRC_PING_INTERVAL', default: 120),
        pongTimeoutSeconds: (int) env('IRC_PONG_TIMEOUT', default: 30),
    ),
    floodProtection: new FloodProtectionConfig(
        burstMessages: (int) env('IRC_FLOOD_BURST_MESSAGES', default: 20),
        messagesPerSecond: (int) env('IRC_FLOOD_MESSAGES_PER_SECOND', default: 2),
    ),
);
