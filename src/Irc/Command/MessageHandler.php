<?php

namespace PhpIrc\Irc\Command;

use PhpIrc\Irc\Protocol\Message;

interface MessageHandler
{
    public function handle(CommandContext $context, Message $message): void;
}
