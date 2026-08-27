<?php

namespace PhpIrc\Application\Console;

use PhpIrc\Irc\Transport\Amp\IrcServer;
use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;

final class ServeIrcCommand
{
    public function __construct(
        private Console $console,
        private IrcServer $server,
    ) {}

    #[ConsoleCommand(
        name: 'irc:serve',
        description: 'Start the IRC server',
    )]
    public function __invoke(): void
    {
        $this->console->info('Starting IRC server');
        $this->server->run();
    }
}
