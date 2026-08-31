# PHP IRC Server

A small, modern IRC server written in PHP 8.5 as a learning and portfolio project. It uses [Tempest](https://tempestphp.com) for the application and dependency-injection layer, and [Amp](https://amphp.org) with [Revolt](https://revolt.run) for asynchronous networking.

The aim is a focused, single-server implementation that works with normal IRC clients without attempting to reproduce every legacy or server-to-server feature. Live clients and channels are held in memory, with boundaries that leave room for persistence or alternative transports later.

## What works

- [x] IRC message parsing and encoding, including message tags
- [x] TCP listener, line buffering, message-size validation and connection cleanup
- [x] Automatic command-handler discovery and dispatch
- [x] `CAP LS` and `CAP END` registration negotiation
- [x] `NICK`, including validation, collisions and nickname changes
- [x] `USER` and client registration
- [x] `PING` responses
- [x] Registration welcome numerics and `005` feature advertisement
- [x] Unknown-command and not-registered responses
- [x] ASCII IRC casemapping for nicknames and channels
- [x] In-memory channel, membership and channel-registry foundations
- [x] Unit and integration test suite

Channel state currently exists internally but is not yet exposed through IRC commands.

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

- [ ] Client delivery and channel broadcasting
- [ ] `JOIN` and `NAMES`, including channel numerics
- [ ] Channel cleanup when clients disconnect
- [ ] `PART` and `QUIT`
- [ ] `PRIVMSG` and `NOTICE` for clients and channels
- [ ] Topics, channel modes and operator commands such as `KICK`
- [ ] Multiple listeners and TLS
- [ ] Broader IRCv3 capability support
- [ ] Optional persistence where it provides value

## Licence

Licensed under the [MIT Licence](LICENSE).
