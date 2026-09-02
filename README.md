# PHP IRC Server

A small, modern IRC server written in PHP 8.5 as a learning and portfolio project. It uses [Tempest](https://tempestphp.com) for the application and dependency-injection layer, and [Amp](https://amphp.org) with [Revolt](https://revolt.run) for asynchronous networking.

The aim is a focused, single-server implementation that works with normal IRC clients without attempting to reproduce every legacy or server-to-server feature. Live clients and channels are held in memory, with boundaries that leave room for persistence or alternative transports later.

## What works

- [x] IRC message parsing and encoding, including message tags
- [x] TCP listener, line buffering, message-size validation and connection cleanup
- [x] Automatic command-handler discovery and dispatch
- [x] Client registration with `CAP LS`, `CAP END`, `NICK` and `USER`
- [x] Nickname validation, collision detection and nickname changes
- [x] Registration welcome messages and `005` feature advertisement
- [x] `PING` and `PONG`
- [x] Joining and leaving channels with `JOIN` and `PART`
- [x] Channel member lists with `NAMES`, including operator prefixes
- [x] Channel discovery with `LIST`
- [x] `PRIVMSG` and `NOTICE` delivery to users and channels, including multiple targets
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
LISTEN_ADDRESS=127.0.0.1
LISTEN_PORT=6667
```

Run all quality checks with:

```shell
composer qa
```

## Roadmap

- [ ] Server keepalive with `PING`/`PONG`, ping timeouts and stale-connection cleanup
- [ ] Per-client flood protection, rate limits and slow-client handling
- [ ] Channel topics with `TOPIC`
- [ ] Channel and membership modes
- [ ] Channel operator commands such as `KICK`
- [ ] Multiple listeners and TLS
- [ ] Broader IRCv3 capability support
- [ ] Optional persistence where it provides value

## Licence

Licensed under the [MIT Licence](LICENSE).
