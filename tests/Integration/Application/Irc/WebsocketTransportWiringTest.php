<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Irc;

use PhpIrc\Irc\Transport\Amp\AmpClientConnectionSupervisor;
use PhpIrc\Irc\Transport\ClientConnectionSupervisor;
use PhpIrc\Irc\Transport\Websocket\DisabledWebsocketServer;
use PhpIrc\Irc\Transport\Websocket\WebsocketServer;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class WebsocketTransportWiringTest extends IntegrationTestCase
{
    #[Test]
    public function it_shares_the_amp_connection_supervisor_between_transports(): void
    {
        $first = $this->container->get(ClientConnectionSupervisor::class);
        $second = $this->container->get(ClientConnectionSupervisor::class);

        $this->assertInstanceOf(AmpClientConnectionSupervisor::class, $first);
        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_disables_websockets_by_default(): void
    {
        $this->assertInstanceOf(
            DisabledWebsocketServer::class,
            $this->container->get(WebsocketServer::class),
        );
    }
}
