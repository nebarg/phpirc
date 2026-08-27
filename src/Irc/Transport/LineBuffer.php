<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLong;

final class LineBuffer
{
    private const int MAX_INCOMPLETE_BYTES = ClientMessageSizeValidator::MAX_TAG_BYTES
        + ClientMessageSizeValidator::MAX_MAIN_BYTES
        + 3;

    private string $buffer = '';

    public function __construct(private readonly ClientMessageSizeValidator $validator) {}

    /**
     * @return list<string>
     *
     * @throws InputTooLong
     */
    public function push(string $bytes): array
    {
        $this->buffer .= $bytes;
        $messages = $this->extractMessages();

        if (strlen($this->buffer) > self::MAX_INCOMPLETE_BYTES) {
            throw new InputTooLong('Input exceeded the maximum bytes.');
        }

        foreach ($messages as $message) {
            $this->validator->validate($message);
        }

        return $messages;
    }

    private function extractMessages(): array
    {
        $result = [];

        while (($messageEnd = strpos($this->buffer, "\r\n")) !== false) {
            $message = substr($this->buffer, 0, $messageEnd);
            $this->buffer = substr($this->buffer, $messageEnd + 2);

            if ($message !== '') {
                $result[] = $message;
            }
        }

        return $result;
    }
}
