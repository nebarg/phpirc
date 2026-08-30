<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Command;

/** Marks a command as available before client registration is complete. */
interface PreRegistrationCommandHandler extends CommandHandler {}
