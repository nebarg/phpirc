<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp\Websocket;

use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use League\Uri\Http;
use PhpIrc\Irc\Config\WebsocketConfig;
use PhpIrc\Irc\Transport\Amp\Websocket\IrcWebsocketAcceptor;
use PhpIrc\Irc\Transport\Websocket\IrcWebsocketProtocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IrcWebsocketAcceptorTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function supportedProtocols(): iterable
    {
        yield 'text' => [IrcWebsocketProtocol::Text->value, IrcWebsocketProtocol::Text->value];
        yield 'binary' => [IrcWebsocketProtocol::Binary->value, IrcWebsocketProtocol::Binary->value];
        yield 'first supported protocol requested by the client' => [
            'unknown.example, binary.ircv3.net, text.ircv3.net',
            IrcWebsocketProtocol::Binary->value,
        ];
    }

    #[Test]
    #[DataProvider('supportedProtocols')]
    public function it_accepts_supported_irc_subprotocols(
        string $requestedProtocols,
        string $expectedProtocol,
    ): void {
        $response = $this->acceptor()->handleHandshake(
            $this->request(protocols: $requestedProtocols),
        );

        $this->assertSame(HttpStatus::SWITCHING_PROTOCOLS, $response->getStatus());
        $this->assertSame($expectedProtocol, $response->getHeader('sec-websocket-protocol'));
    }

    #[Test]
    public function it_rejects_requests_for_another_path(): void
    {
        $response = $this->acceptor()->handleHandshake($this->request(path: '/wrong'));

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
    }

    #[Test]
    public function it_rejects_untrusted_origins(): void
    {
        $response = $this->acceptor()->handleHandshake(
            $this->request(origin: 'https://malicious.example'),
        );

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    #[Test]
    public function it_rejects_requests_without_a_supported_subprotocol(): void
    {
        $response = $this->acceptor()->handleHandshake(
            $this->request(protocols: 'chat.example'),
        );

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    #[Test]
    public function it_can_explicitly_allow_any_origin(): void
    {
        $response = $this->acceptor(['*'])->handleHandshake(
            $this->request(origin: 'https://chat.example.com'),
        );

        $this->assertSame(HttpStatus::SWITCHING_PROTOCOLS, $response->getStatus());
    }

    /** @param non-empty-list<non-empty-string> $origins */
    private function acceptor(array $origins = ['https://app.example.com']): IrcWebsocketAcceptor
    {
        return new IrcWebsocketAcceptor(
            new WebsocketConfig('127.0.0.1', 8081, '/irc', $origins),
            new DefaultErrorHandler(),
        );
    }

    private function request(
        string $path = '/irc',
        string $origin = 'https://app.example.com',
        string $protocols = 'text.ircv3.net',
    ): Request {
        return new Request(
            client: $this->createStub(Client::class),
            method: 'GET',
            uri: Http::new("http://localhost{$path}"),
            headers: [
                'connection' => 'Upgrade',
                'upgrade' => 'websocket',
                'sec-websocket-key' => 'dGhlIHNhbXBsZSBub25jZQ==',
                'sec-websocket-version' => '13',
                'sec-websocket-protocol' => $protocols,
                'origin' => $origin,
            ],
        );
    }
}
