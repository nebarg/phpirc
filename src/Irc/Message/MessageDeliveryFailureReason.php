<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Message;

enum MessageDeliveryFailureReason
{
    case NoSuchNickname;
    case CannotSendToChannel;
}
