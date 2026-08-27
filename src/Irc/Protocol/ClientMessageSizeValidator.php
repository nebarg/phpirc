<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol;

final class ClientMessageSizeValidator
{
    public const int MAX_TAG_BYTES = 4094;

    public const int MAX_MAIN_BYTES = 510;

    /**
     * @throws InputTooLongException
     */
    public function validate(string $line): void
    {
        if (! str_starts_with($line, '@')) {
            $this->validateMainSection($line);

            return;
        }

        $tagSeparator = strpos($line, ' ');
        $tagData = $tagSeparator === false
            ? substr($line, 1)
            : substr($line, 1, $tagSeparator - 1);

        if (strlen($tagData) > self::MAX_TAG_BYTES) {
            throw new InputTooLongException(sprintf(
                'Tags cannot be greater than %d bytes',
                self::MAX_TAG_BYTES
            ));
        }

        if ($tagSeparator !== false) {
            $this->validateMainSection(substr($line, $tagSeparator + 1));
        }
    }

    /**
     * @throws InputTooLongException
     */
    private function validateMainSection(string $main): void
    {
        if (strlen($main) > self::MAX_MAIN_BYTES) {
            throw new InputTooLongException(sprintf(
                'Message cannot be greater than %d bytes',
                self::MAX_MAIN_BYTES
            ));
        }
    }
}
