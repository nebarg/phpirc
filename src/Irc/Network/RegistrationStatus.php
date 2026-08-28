<?php

namespace PhpIrc\Irc\Network;

enum RegistrationStatus
{
    case Pending;
    case WaitingForCapEnd;
    case Complete;
}
