<?php

use PhpIrc\Irc\Config\FloodProtectionConfig;
use PhpIrc\Irc\Config\KeepaliveConfig;
use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ListenerTlsConfig;
use PhpIrc\Irc\Config\OutboundQueueConfig;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;

use function Tempest\env;
use function Tempest\root_path;
use function Tempest\Support\Path\is_absolute_path;

$motdFile = (string) env('IRC_MOTD_FILE', default: 'motd');
$listenAddress = (string) env('LISTEN_ADDRESS', default: '127.0.0.1');
$listenPort = (int) env('LISTEN_PORT', default: 6667);
$tlsListenPort = (int) env('TLS_LISTEN_PORT', default: 0);
$listeners = [];

if ($listenPort !== 0) {
    $listeners[] = new ListenerConfig(
        address: $listenAddress,
        port: $listenPort,
    );
}

if ($tlsListenPort !== 0) {
    $certificateFile = (string) env('TLS_CERTIFICATE_FILE', default: '');
    $privateKeyFile = (string) env('TLS_PRIVATE_KEY_FILE', default: '');

    $listeners[] = new ListenerConfig(
        address: $listenAddress,
        port: $tlsListenPort,
        tls: new ListenerTlsConfig(
            certificateFile: $certificateFile === '' || is_absolute_path($certificateFile)
                ? $certificateFile
                : root_path($certificateFile),
            privateKeyFile: $privateKeyFile === '' || is_absolute_path($privateKeyFile)
                ? $privateKeyFile
                : root_path($privateKeyFile),
            handshakeTimeoutSeconds: (int) env('TLS_HANDSHAKE_TIMEOUT', default: 10),
        ),
    );
}

return new ServerConfig(
    serverName: new ServerName((string) env('IRC_SERVER_NAME', default: 'irc.local')),
    networkName: (string) env('IRC_NETWORK_NAME', default: 'PHPIRC'),
    listeners: $listeners,
    softwareVersion: (string) env('IRC_SERVER_VERSION', default: 'phpirc-0.1.0'),
    keepalive: new KeepaliveConfig(
        pingIntervalSeconds: (int) env('IRC_PING_INTERVAL', default: 120),
        pongTimeoutSeconds: (int) env('IRC_PONG_TIMEOUT', default: 30),
    ),
    floodProtection: new FloodProtectionConfig(
        burstMessages: (int) env('IRC_FLOOD_BURST_MESSAGES', default: 20),
        messagesPerSecond: (int) env('IRC_FLOOD_MESSAGES_PER_SECOND', default: 2),
    ),
    outboundQueue: new OutboundQueueConfig(
        maximumBytes: (int) env('IRC_OUTBOUND_QUEUE_BYTES', default: 262_144),
    ),
    motdFile: is_absolute_path($motdFile) ? $motdFile : root_path($motdFile),
);
