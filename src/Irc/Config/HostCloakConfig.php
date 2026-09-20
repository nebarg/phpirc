<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Config;

use InvalidArgumentException;

final readonly class HostCloakConfig
{
    /** Hex characters kept from the digest, leaving 64 bits of collision space. */
    public const int DIGEST_LENGTH = 16;

    /**
     * What is left of a hostname once the digest and its separator are spent.
     *
     * @var int<1, max>
     */
    public const int MAX_SUFFIX_BYTES = ServerLimits::MAX_HOSTNAME_BYTES - self::DIGEST_LENGTH - 1;

    public function __construct(
        public bool $enabled = false,
        public string $secret = '',
        public string $suffix = 'cloak',
    ) {
        if (! $enabled) {
            return;
        }

        if ($secret === '') {
            throw new InvalidArgumentException(
                'A host cloak secret is required, because an unkeyed digest of an address can be undone by hashing every address.',
            );
        }

        if (preg_match('~\A[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\z~', $suffix) !== 1) {
            throw new InvalidArgumentException(
                'Host cloak suffix must be letters, digits, dots and hyphens, starting and ending with a letter or digit.',
            );
        }

        if (strlen($suffix) > self::MAX_SUFFIX_BYTES) {
            throw new InvalidArgumentException(
                'Host cloak suffix cannot be longer than ' . self::MAX_SUFFIX_BYTES . ' bytes.',
            );
        }
    }
}
