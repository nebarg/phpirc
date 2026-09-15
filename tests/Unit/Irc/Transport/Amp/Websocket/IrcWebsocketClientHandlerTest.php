<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp\Websocket;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Middleware\Forwarded;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Socket\InternetAddress;
use Amp\Websocket\WebsocketClient;
use League\Uri\Http;
use PhpIrc\Irc\Transport\Amp\Websocket\AmpWebsocketClientSocket;
use PhpIrc\Irc\Transport\Amp\Websocket\IrcWebsocketClientHandler;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\ClientSocket;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IrcWebsocketClientHandlerTest extends TestCase
{
    #[Test]
    public function it_runs_the_negotiated_websocket_as_an_irc_client_connection(): void
    {
        $websocketClient = $this->createStub(WebsocketClient::class);
        $connections = $this->createMock(ClientConnectionSupervisor::class);
        $connections
            ->expects($this->once())
            ->method('run')
            ->with($this->callback(
                static function (ClientSocket $socket): bool {
                    if (! $socket instanceof AmpWebsocketClientSocket) {
                        return false;
                    }

                    return $socket->remoteAddress() === '203.0.113.10';
                },
            ));
        $request = new Request(
            $this->createStub(Client::class),
            'GET',
            Http::new('http://localhost/irc'),
        );
        $request->setAttribute(
            Forwarded::class,
            new Forwarded(new InternetAddress('203.0.113.10', 0), [
                'for' => '203.0.113.10',
            ]),
        );
        $response = new Response(headers: [
            'sec-websocket-protocol' => 'text.ircv3.net',
        ]);

        new IrcWebsocketClientHandler($connections)->handleClient(
            $websocketClient,
            $request,
            $response,
        );
    }
}
