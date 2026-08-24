<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class MessageParser
{
    /**
     * Walk the message string as extra spaces are valid
     *
     * @throws InvalidMessage
     */
    public function parse(string $line): Message
    {
        $this->validateLine($line);

        $length = strlen($line);
        $position = 0;
        $tags = [];

        if ($line[$position] === '@') {
            [$tags, $position] = $this->parseTags($line, $position);
            $position = $this->skipSpaces($line, $position);
        }

        if ($position >= $length) {
            throw new InvalidMessage('Message does not contain a command.');
        }

        $source = null;

        if ($line[$position] === ':') {
            [$source, $position] = $this->parseSource($line, $position);
            $position = $this->skipSpaces($line, $position);
        }

        if ($position >= $length) {
            throw new InvalidMessage('Message does not contain a command.');
        }

        [$command, $position] = $this->parseCommand($line, $position);
        $parameters = $this->parseParameters($line, $position);

        return new Message(
            tags: $tags,
            source: $source,
            command: $command,
            parameters: $parameters,
        );
    }

    private function validateLine(string $line): void
    {
        if ($line === '') {
            throw new InvalidMessage('Message cannot be empty.');
        }

        if ($line[0] === ' ') {
            throw new InvalidMessage('Message cannot begin with a space.');
        }

        if (strpbrk($line, "\0\r\n") !== false) {
            throw new InvalidMessage('Message contains a forbidden byte.');
        }
    }

    /**
     * @return array{list<MessageTag>, int}
     */
    private function parseTags(string $line, int $position): array
    {
        $tagsEnd = strpos($line, ' ', $position);

        if ($tagsEnd === false) {
            throw new InvalidMessage('Tag section is not followed by a command.');
        }

        $tagSection = substr($line, $position + 1, $tagsEnd - $position - 1);

        /** @var array<string, MessageTag> $tagsByName */
        $tagsByName = [];

        foreach (explode(';', $tagSection) as $rawTag) {
            $valueDelimiter = strpos($rawTag, '=');

            if ($valueDelimiter === false) {
                $name = $rawTag;
                $value = null;
            } else {
                $name = substr($rawTag, 0, $valueDelimiter);
                $escapedValue = substr($rawTag, $valueDelimiter + 1);
                $value = $escapedValue === ''
                    ? null
                    : $this->unescapeTagValue($escapedValue);
            }

            // Prefixing avoids PHP converting numeric string keys to integers.
            $tagsByName[':' . $name] = new MessageTag($name, $value);
        }

        return [array_values($tagsByName), $tagsEnd];
    }

    /**
     * @return array{string, int}
     */
    private function parseSource(string $line, int $position): array
    {
        $sourceStart = $position + 1;
        $sourceEnd = strpos($line, ' ', $sourceStart);

        if ($sourceEnd === false) {
            throw new InvalidMessage('Source is not followed by a command.');
        }

        if ($sourceEnd === $sourceStart) {
            throw new InvalidMessage('Source cannot be empty.');
        }

        return [
            substr($line, $sourceStart, $sourceEnd - $sourceStart),
            $sourceEnd,
        ];
    }

    /**
     * @return array{string, int}
     */
    private function parseCommand(string $line, int $position): array
    {
        $commandEnd = strpos($line, ' ', $position);

        if ($commandEnd === false) {
            $commandEnd = strlen($line);
        }

        $command = substr($line, $position, $commandEnd - $position);

        if (preg_match('/\A(?:[A-Za-z]+|[0-9]{3})\z/', $command) !== 1) {
            throw new InvalidMessage('Message contains an invalid command.');
        }

        return [strtoupper($command), $commandEnd];
    }

    /**
     * @return list<string>
     */
    private function parseParameters(string $line, int $position): array
    {
        $length = strlen($line);
        $parameters = [];

        while ($position < $length) {
            $position = $this->skipSpaces($line, $position);

            if ($position >= $length) {
                break;
            }

            if ($line[$position] === ':') {
                $parameters[] = substr($line, $position + 1);

                break;
            }

            $parameterEnd = strpos($line, ' ', $position);

            if ($parameterEnd === false) {
                $parameterEnd = $length;
            }

            $parameters[] = substr($line, $position, $parameterEnd - $position);
            $position = $parameterEnd;
        }

        return $parameters;
    }

    private function skipSpaces(string $line, int $position): int
    {
        $length = strlen($line);

        while ($position < $length && $line[$position] === ' ') {
            $position++;
        }

        return $position;
    }

    private function unescapeTagValue(string $value): string
    {
        $unescaped = '';
        $length = strlen($value);

        for ($position = 0; $position < $length; $position++) {
            $character = $value[$position];

            if ($character !== '\\') {
                $unescaped .= $character;

                continue;
            }

            $position++;

            if ($position >= $length) {
                break;
            }

            $escapedCharacter = $value[$position];

            $unescaped .= match ($escapedCharacter) {
                ':' => ';',
                's' => ' ',
                '\\' => '\\',
                'r' => "\r",
                'n' => "\n",
                default => $escapedCharacter,
            };
        }

        return $unescaped;
    }
}
