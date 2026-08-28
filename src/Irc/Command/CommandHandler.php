<?php

namespace PhpIrc\Irc\Command;

interface CommandHandler extends MessageHandler
{
    public function command(): string;
}
