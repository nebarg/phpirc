<?php

namespace PhpIrc\Irc\Client\Registration;

enum RegistrationStatus
{
    case Pending;
    case WaitingForCapEnd;
    case Complete;
}
