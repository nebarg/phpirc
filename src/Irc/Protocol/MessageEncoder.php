<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final readonly class MessageEncoder
{
    /**
     * @throws InvalidMessageException
     */
    public function encode(Message $message): string
    {
        return $this->encodeTags($message->tags)
        . $this->encodeSource($message->source)
        . $this->encodeCommand($message->command)
        . $this->encodeParameters($message->parameters)
        . "\r\n";
    }

    /** @param list<MessageTag> $tags */
    private function encodeTags(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        $result = [];
        foreach ($tags as $tag) {
            if ($tag->value === null) {
                $result[] = $tag->name;
                continue;
            }

            $encodedValue = strtr($tag->value, [
                '\\' => '\\\\',
                ';' => '\:',
                ' ' => '\s',
                "\r" => '\r',
                "\n" => '\n',
            ]);

            $result[] = "{$tag->name}={$encodedValue}";
        }

        return '@' . implode(';', $result) . ' ';
    }

    private function encodeSource(null|string $source): string
    {
        if ($source === null) {
            return '';
        }

        if ($source === '') {
            throw new InvalidMessageException('Message source cannot be empty.');
        }

        if (preg_match('/[\x00\r\n ]/', $source) !== 0) {
            throw new InvalidMessageException('Message contains an invalid character.');
        }

        return ":$source ";
    }

    private function encodeCommand(string $command): string
    {
        if (preg_match('/\A(?:[A-Za-z]+|[0-9]{3})\z/', $command) !== 1) {
            throw new InvalidMessageException('Message contains an invalid command.');
        }

        return strtoupper($command);
    }

    /** @param list<string> $parameters */
    private function encodeParameters(array $parameters): string
    {
        if ($parameters === []) {
            return '';
        }

        $last = array_key_last($parameters);
        $result = [];

        foreach ($parameters as $index => $parameter) {
            if (preg_match('/[\x00\r\n]/', $parameter) !== 0) {
                throw new InvalidMessageException('Parameter contains an invalid character.');
            }

            if ($index === $last) {
                $needsColon = $parameter === ''
                    || $parameter[0] === ':'
                    || str_contains($parameter, ' ');

                $result[] = $needsColon ? ":$parameter" : $parameter;
                break;
            }

            if ($parameter === '' || $parameter[0] === ':' || str_contains($parameter, ' ')) {
                throw new InvalidMessageException('Invalid parameter.');
            }

            $result[] = $parameter;
        }

        return ' ' . implode(' ', $result);
    }
}
