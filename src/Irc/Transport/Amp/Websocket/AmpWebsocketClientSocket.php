<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport\Amp\Websocket;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Socket\InternetAddress;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketCloseCode;
use Amp\Websocket\WebsocketClosedException;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Transport\ClientSocket;
use PhpIrc\Irc\Transport\ClientSocketException;
use PhpIrc\Irc\Transport\Websocket\IrcWebsocketProtocol;

final readonly class AmpWebsocketClientSocket implements ClientSocket
{
    // @mago-format-ignore-next
    public const int MAX_PAYLOAD_BYTES = 1
        + ClientMessageSizeValidator::MAX_TAG_BYTES
        + 1
        + ClientMessageSizeValidator::MAX_MAIN_BYTES;

    public function __construct(
        private WebsocketClient $client,
        private IrcWebsocketProtocol $protocol,
        private ?string $forwardedRemoteAddress = null,
    ) {}

    public function remoteAddress(): string
    {
        if ($this->forwardedRemoteAddress !== null) {
            return $this->forwardedRemoteAddress;
        }

        $address = $this->client->getRemoteAddress();

        return $address instanceof InternetAddress
            ? $address->getAddress()
            : $address->toString();
    }

    public function read(): ?string
    {
        try {
            $message = $this->client->receive();

            if ($message === null) {
                return null;
            }

            if ($this->protocol === IrcWebsocketProtocol::Text && ! $message->isText()) {
                $this->fail(
                    WebsocketCloseCode::UNACCEPTABLE_TYPE,
                    'Expected a text IRC message.',
                );
            }

            if ($this->protocol === IrcWebsocketProtocol::Binary && ! $message->isBinary()) {
                $this->fail(
                    WebsocketCloseCode::UNACCEPTABLE_TYPE,
                    'Expected a binary IRC message.',
                );
            }

            $payload = $message->buffer(limit: self::MAX_PAYLOAD_BYTES);

            if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
                $this->fail(
                    WebsocketCloseCode::MESSAGE_TOO_LARGE,
                    'IRC WebSocket message is too large.',
                );
            }

            if ($payload === '') {
                $this->fail(
                    WebsocketCloseCode::PROTOCOL_ERROR,
                    'A WebSocket message must contain one IRC line.',
                );
            }

            if (str_contains($payload, "\r") || str_contains($payload, "\n")) {
                $this->fail(
                    WebsocketCloseCode::PROTOCOL_ERROR,
                    'Each WebSocket message must contain exactly one IRC line without a terminator.',
                );
            }

            if ($this->protocol === IrcWebsocketProtocol::Text && preg_match('//u', $payload) !== 1) {
                $this->fail(
                    WebsocketCloseCode::INCONSISTENT_FRAME_DATA_TYPE,
                    'Text IRC WebSocket messages must contain valid UTF-8.',
                );
            }

            return "{$payload}\r\n";
        } catch (BufferException $exception) {
            $this->client->close(
                WebsocketCloseCode::MESSAGE_TOO_LARGE,
                'IRC WebSocket message is too large.',
            );

            throw new ClientSocketException(
                'IRC WebSocket message is too large.',
                previous: $exception,
            );
        } catch (StreamException $exception) {
            throw new ClientSocketException(
                'Failed to read from the WebSocket client.',
                previous: $exception,
            );
        } catch (WebsocketClosedException $exception) {
            throw new ClientSocketException(
                'Failed to read from the WebSocket client.',
                previous: $exception,
            );
        }
    }

    public function write(string $bytes): void
    {
        if (! str_ends_with($bytes, "\r\n")) {
            throw new ClientSocketException('Outbound IRC WebSocket messages must end with CRLF.');
        }

        $payload = substr($bytes, 0, -2);

        if (str_contains($payload, "\r") || str_contains($payload, "\n")) {
            throw new ClientSocketException('Outbound WebSocket frames must contain exactly one IRC line.');
        }

        if ($this->protocol === IrcWebsocketProtocol::Text && preg_match('//u', $payload) !== 1) {
            $this->fail(
                WebsocketCloseCode::INCONSISTENT_FRAME_DATA_TYPE,
                'Text IRC WebSocket messages must contain valid UTF-8.',
            );
        }

        try {
            match ($this->protocol) {
                IrcWebsocketProtocol::Text => $this->client->sendText($payload),
                IrcWebsocketProtocol::Binary => $this->client->sendBinary($payload),
            };
        } catch (WebsocketClosedException $exception) {
            throw new ClientSocketException(
                'Failed to write to the WebSocket client.',
                previous: $exception,
            );
        }
    }

    public function close(): void
    {
        if (! $this->client->isClosed()) {
            $this->client->close(WebsocketCloseCode::NORMAL_CLOSE, 'IRC connection closed.');
        }
    }

    /** @return never */
    private function fail(int $closeCode, string $reason): never
    {
        $this->client->close($closeCode, $reason);

        throw new ClientSocketException($reason);
    }
}
