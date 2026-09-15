<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Transport\Amp\Websocket;

use Amp\Socket\InternetAddress;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketCloseCode;
use Amp\Websocket\WebsocketClosedException;
use Amp\Websocket\WebsocketMessage;
use PhpIrc\Irc\Transport\Amp\Websocket\AmpWebsocketClientSocket;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\Websocket\IrcWebsocketProtocol;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AmpWebsocketClientSocketTest extends TestCase
{
    #[Test]
    public function it_adapts_a_text_message_to_an_irc_line(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client
            ->expects($this->once())
            ->method('receive')
            ->willReturn(
                WebsocketMessage::fromText('PRIVMSG #php :Hello'),
            );

        $this->assertSame(
            "PRIVMSG #php :Hello\r\n",
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read(),
        );
    }

    #[Test]
    public function it_adapts_a_binary_message_to_an_irc_line(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client
            ->expects($this->once())
            ->method('receive')
            ->willReturn(
                WebsocketMessage::fromBinary('PING :token'),
            );

        $this->assertSame(
            "PING :token\r\n",
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Binary)->read(),
        );
    }

    #[Test]
    public function it_returns_null_when_the_websocket_closes(): void
    {
        $client = $this->createStub(WebsocketClient::class);
        $client->method('receive')->willReturn(null);

        $this->assertNull(
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read(),
        );
    }

    #[Test]
    public function it_removes_the_irc_line_terminator_from_outbound_text_frames(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->expects($this->once())->method('sendText')->with('PONG :token');
        $client->expects($this->never())->method('sendBinary');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)
            ->write("PONG :token\r\n");
    }

    #[Test]
    public function it_removes_the_irc_line_terminator_from_outbound_binary_frames(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->expects($this->once())->method('sendBinary')->with('PONG :token');
        $client->expects($this->never())->method('sendText');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Binary)
            ->write("PONG :token\r\n");
    }

    #[Test]
    public function it_uses_the_remote_ip_address_without_the_port(): void
    {
        $client = $this->createStub(WebsocketClient::class);
        $client->method('getRemoteAddress')->willReturn(new InternetAddress('203.0.113.10', 8081));

        $this->assertSame(
            '203.0.113.10',
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->remoteAddress(),
        );
    }

    #[Test]
    public function it_rejects_the_wrong_websocket_message_type(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->method('receive')->willReturn(WebsocketMessage::fromBinary('PING'));
        $client
            ->expects($this->once())
            ->method('close')
            ->with(WebsocketCloseCode::UNACCEPTABLE_TYPE, 'Expected a text IRC message.');

        $this->expectException(ClientSocketException::class);
        $this->expectExceptionMessage('Expected a text IRC message.');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
    }

    #[Test]
    public function it_rejects_messages_containing_line_terminators(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->method('receive')->willReturn(WebsocketMessage::fromText("PING\r\nPONG"));
        $client
            ->expects($this->once())
            ->method('close')
            ->with(
                WebsocketCloseCode::PROTOCOL_ERROR,
                'Each WebSocket message must contain exactly one IRC line without a terminator.',
            );

        $this->expectException(ClientSocketException::class);

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
    }

    #[Test]
    public function it_rejects_empty_messages(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->method('receive')->willReturn(WebsocketMessage::fromText(''));
        $client
            ->expects($this->once())
            ->method('close')
            ->with(
                WebsocketCloseCode::PROTOCOL_ERROR,
                'A WebSocket message must contain one IRC line.',
            );

        $this->expectException(ClientSocketException::class);

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
    }

    #[Test]
    public function it_rejects_invalid_utf8_text_messages(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->method('receive')->willReturn(WebsocketMessage::fromText("\xFF"));
        $client
            ->expects($this->once())
            ->method('close')
            ->with(
                WebsocketCloseCode::INCONSISTENT_FRAME_DATA_TYPE,
                'Text IRC WebSocket messages must contain valid UTF-8.',
            );

        $this->expectException(ClientSocketException::class);

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
    }

    #[Test]
    public function it_rejects_oversized_messages(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client
            ->method('receive')
            ->willReturn(WebsocketMessage::fromText(
                str_repeat('x', 4_607),
            ));
        $client
            ->expects($this->once())
            ->method('close')
            ->with(
                WebsocketCloseCode::MESSAGE_TOO_LARGE,
                'IRC WebSocket message is too large.',
            );

        $this->expectException(ClientSocketException::class);
        $this->expectExceptionMessage('IRC WebSocket message is too large.');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
    }

    #[Test]
    public function it_translates_websocket_read_failures(): void
    {
        $cause = new WebsocketClosedException('Read failed', 1000, 'Closed');
        $client = $this->createStub(WebsocketClient::class);
        $client->method('receive')->willThrowException($cause);

        try {
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->read();
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to read from the WebSocket client.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_translates_websocket_write_failures(): void
    {
        $cause = new WebsocketClosedException('Write failed', 1000, 'Closed');
        $client = $this->createStub(WebsocketClient::class);
        $client->method('sendText')->willThrowException($cause);

        try {
            new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)
                ->write("PING\r\n");
            $this->fail('Expected a client socket exception.');
        } catch (ClientSocketException $exception) {
            $this->assertSame('Failed to write to the WebSocket client.', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_rejects_invalid_outbound_framing(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->expects($this->never())->method('sendText');

        $this->expectException(ClientSocketException::class);
        $this->expectExceptionMessage('Outbound WebSocket frames must contain exactly one IRC line.');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)
            ->write("PING\r\nPONG\r\n");
    }

    #[Test]
    public function it_rejects_invalid_utf8_in_outbound_text_frames(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->expects($this->never())->method('sendText');
        $client
            ->expects($this->once())
            ->method('close')
            ->with(
                WebsocketCloseCode::INCONSISTENT_FRAME_DATA_TYPE,
                'Text IRC WebSocket messages must contain valid UTF-8.',
            );

        $this->expectException(ClientSocketException::class);

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)
            ->write("\xFF\r\n");
    }

    #[Test]
    public function it_closes_an_open_websocket(): void
    {
        $client = $this->createMock(WebsocketClient::class);
        $client->method('isClosed')->willReturn(false);
        $client
            ->expects($this->once())
            ->method('close')
            ->with(WebsocketCloseCode::NORMAL_CLOSE, 'IRC connection closed.');

        new AmpWebsocketClientSocket($client, IrcWebsocketProtocol::Text)->close();
    }
}
