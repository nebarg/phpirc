<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

use PhpIrc\Irc\Client\Client;

final readonly class MessageDeliveryReport
{
    /**
     * @param list<MessageDeliveryFailure> $failures
     * @param list<Client> $awayRecipients
     */
    public function __construct(
        public array $failures = [],
        public array $awayRecipients = [],
    ) {}
}
