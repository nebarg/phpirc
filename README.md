# PHP IRC Server

A small, modern IRC server written in PHP 8.5 as a learning and portfolio project. It uses [Tempest](https://tempestphp.com) for the application and dependency-injection layer, and [Amp](https://amphp.org) with [Revolt](https://revolt.run) for asynchronous networking.

The aim is a focused, single-server implementation that works with normal IRC clients without attempting to reproduce every legacy or server-to-server feature. Live clients and channels are held in memory, with boundaries that leave room for persistence or alternative transports later.

## What works

- [x] IRC message parsing and encoding, including message tags
- [x] TCP listener, line buffering, inbound message-size validation and connection cleanup
- [x] Concurrent plaintext and implicit-TLS listeners
- [x] Native IRCv3 WebSocket transport for browser clients
- [x] Automatic command-handler discovery and dispatch
- [x] Client registration with `CAP LS`, `CAP END`, `NICK` and `USER`
- [x] IRCv3 `server-time` capability negotiation and timestamped server messages
- [x] Nickname validation, collision detection and nickname changes
- [x] Registration welcome messages and `005` feature advertisement
- [x] Configurable message of the day during registration and with `MOTD`
- [x] Client and server `PING`/`PONG`, including stale-connection timeouts
- [x] Per-client command-rate limits and excess-flood disconnections
- [x] Per-client bounded outbound queues and slow-client disconnections
- [x] Graceful `SIGINT`/`SIGTERM` shutdown with client notification and connection draining
- [x] Joining and leaving channels with `JOIN` and `PART`
- [x] Channel member lists with `NAMES`, including operator prefixes
- [x] Channel discovery with `LIST`
- [x] Basic `WHO` queries for exact nicknames and channel members
- [x] User information and channel membership queries with `WHOIS`
- [x] Away status with `AWAY`, including `WHO`, `WHOIS` and direct-message replies
- [x] Current, peak and total connection statistics for the current server run with `LUSERS`
- [x] User and channel `MODE` queries
- [x] Invisible user mode changes with `+i` and `-i`, including visibility-aware `WHO` and `NAMES`
- [x] Channel operator and voice changes with `+o`, `-o`, `+v` and `-v`
- [x] Moderated, protected-topic and no-external-message channel modes with `+m`, `+t` and `+n`
- [x] Channel operator removal with `KICK`
- [x] Viewing, setting and clearing channel topics with `TOPIC`
- [x] `PRIVMSG` and `NOTICE` delivery to users and channels, including multiple targets
- [x] Outbound message-size enforcement for server replies and relayed chat
- [x] Unknown-command and not-registered responses
- [x] ASCII IRC casemapping for nicknames and channels
- [x] In-memory client, channel and membership state
- [x] Nickname and channel cleanup when clients disconnect
- [x] `QUIT` and unexpected-disconnect notifications to shared channel members
- [x] Unit and integration test suite

## Running the server

Requirements:

- PHP 8.5
- Composer

Install dependencies and start the server:

```shell
composer install
php tempest irc:serve
```

By default, it listens on `127.0.0.1:6667`. You can connect with an IRC client or test it with netcat:

```shell
nc 127.0.0.1 6667
```

Then register and send a ping:

```irc
NICK John
USER john 0 * :John Doe
PING :hello
```

The defaults can be overridden in `.env`:

```dotenv
IRC_SERVER_NAME=irc.local
IRC_NETWORK_NAME=PHPIRC
IRC_SERVER_VERSION=phpirc-0.1.0
IRC_MOTD_FILE=motd
IRC_PING_INTERVAL=120
IRC_PONG_TIMEOUT=30
IRC_FLOOD_BURST_MESSAGES=20
IRC_FLOOD_MESSAGES_PER_SECOND=2
IRC_OUTBOUND_QUEUE_BYTES=262144
LISTEN_ADDRESS=127.0.0.1
LISTEN_PORT=6667
TLS_LISTEN_PORT=0
TLS_CERTIFICATE_FILE=
TLS_PRIVATE_KEY_FILE=
TLS_HANDSHAKE_TIMEOUT=10
WEBSOCKET_LISTEN_ADDRESS=127.0.0.1
WEBSOCKET_LISTEN_PORT=0
WEBSOCKET_PATH=/irc
WEBSOCKET_ALLOWED_ORIGINS=http://localhost:8000,http://127.0.0.1:8000
WEBSOCKET_TRUSTED_PROXIES=
IRC_HOST_CLOAKING=false
IRC_HOST_CLOAK_SECRET=
IRC_HOST_CLOAK_SUFFIX=cloak
```

Set `TLS_LISTEN_PORT` to `6697` and provide readable certificate-chain and private-key files to enable implicit TLS. Set `LISTEN_PORT` to `0` if the server should accept only TLS connections. Relative certificate paths are resolved from the project root.

### Browser WebSocket clients

Set `WEBSOCKET_LISTEN_PORT` to enable the browser-facing listener. Browser clients must negotiate the IRCv3 `text.ircv3.net` subprotocol and send exactly one IRC line per WebSocket message, without `\r\n`:

```javascript
const irc = new WebSocket('ws://127.0.0.1:8081/irc', 'text.ircv3.net');

irc.addEventListener('open', () => {
    irc.send('NICK Jane');
    irc.send('USER jane 0 * :Jane Doe');
});

irc.addEventListener('message', ({ data }) => {
    console.log(data);
});
```

### Cloaking client addresses

`WHOIS` and `WHO` publish the address a client connected from, which on a public server hands every user's IP address to everyone else who asks. Set `IRC_HOST_CLOAKING=true` to replace it in those replies with a stand-in such as `4f3a2b1c9d8e7f60.cloak`. The server keeps the real address for itself.

`IRC_HOST_CLOAK_SECRET` is required when cloaking is enabled, and the server will refuse to start without it. An address is drawn from a space small enough to hash in full, so an unkeyed digest would name the address it came from; the secret is what makes that infeasible. Use a long random value, keep it out of version control, and note that changing it changes every cloak.

`IRC_HOST_CLOAK_SUFFIX` is the label each cloak ends with, `cloak` by default. Each address gets the same cloak every time, so bans and ignores by host keep working and a user stays recognisable across a nickname change.

`WEBSOCKET_ALLOWED_ORIGINS` is a comma-separated allowlist of exact browser origins. Use the Laravel application's public origin in deployed environments. `*` explicitly permits every origin.

For a public demo, terminate `wss://` at nginx and proxy the upgrade to PHP's local listener:

```nginx
location /irc {
    proxy_pass http://127.0.0.1:8081;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_read_timeout 300s;
}
```

Keep `WEBSOCKET_LISTEN_ADDRESS=127.0.0.1`, set `WEBSOCKET_TRUSTED_PROXIES=127.0.0.1,::1`, and connect the Vue client with `wss://your-domain.example/irc`. This leaves certificate handling with nginx while PHPIRC sees the original client address from the trusted proxy.

Run all quality checks with:

```shell
composer qa
```

## Roadmap

- [ ] Broader IRCv3 capability support
- [ ] Optional persistence where it provides value

## Licence

Licensed under the [MIT Licence](LICENSE).
