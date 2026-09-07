<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

enum MessageDeliveryFailureReason
{
    case TargetNotFound;
    case CannotSendToChannel;
}
