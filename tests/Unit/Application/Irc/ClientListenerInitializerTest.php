<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Irc;

use Amp\Socket\ServerSocket as AmpServerSocket;
use Amp\Socket\ServerSocketFactory;
use LogicException;
use PhpIrc\Application\Irc\ClientListenerInitializer;
use PhpIrc\Irc\Config\ListenerConfig;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Transport\Amp\AmpClientListenerFactory;
use PhpIrc\Irc\Transport\ClientListenerCollection;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tempest\Container\Container;
use Tests\TestCase;

final class ClientListenerInitializerTest extends TestCase
{
    #[Test]
    public function it_requires_at_least_one_listener(): void
    {
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [],
        );
        $container = $this->createMock(Container::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(ServerConfig::class)
            ->willReturn($config);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one listener must be configured.');

        new ClientListenerInitializer()->initialize($container);
    }

    #[Test]
    public function it_combines_multiple_configured_listeners(): void
    {
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [
                new ListenerConfig('127.0.0.1', 6667),
                new ListenerConfig('127.0.0.1', 6697),
            ],
        );
        $container = $this->createStub(Container::class);
        $container
            ->method('get')
            ->willReturn($config);
        $serverSockets = $this->createMock(ServerSocketFactory::class);
        $serverSockets
            ->expects($this->exactly(2))
            ->method('listen')
            ->willReturn(
                $this->createStub(AmpServerSocket::class),
                $this->createStub(AmpServerSocket::class),
            );

        $listener = new ClientListenerInitializer(
            new AmpClientListenerFactory($serverSockets),
        )->initialize($container);

        $this->assertInstanceOf(ClientListenerCollection::class, $listener);
        $this->assertCount(2, $listener->all());
    }

    #[Test]
    public function it_closes_created_listeners_when_a_later_listener_cannot_be_created(): void
    {
        $config = new ServerConfig(
            serverName: new ServerName('irc.test'),
            networkName: 'Test Network',
            listeners: [
                new ListenerConfig('127.0.0.1', 6667),
                new ListenerConfig('127.0.0.1', 6697),
            ],
        );
        $container = $this->createStub(Container::class);
        $container
            ->method('get')
            ->willReturn($config);
        $firstServer = $this->createMock(AmpServerSocket::class);
        $firstServer->expects($this->once())->method('close');
        /** @var list<AmpServerSocket|RuntimeException> $listenResults */
        $listenResults = [$firstServer, new RuntimeException('Unable to bind listener.')];
        $serverSockets = $this->createStub(ServerSocketFactory::class);
        $serverSockets
            ->method('listen')
            ->willReturnCallback(static function () use (&$listenResults): AmpServerSocket {
                $result = array_shift($listenResults);

                if ($result instanceof RuntimeException) {
                    throw $result;
                }

                if ($result === null) {
                    throw new LogicException('No listener result configured.');
                }

                return $result;
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to bind listener.');

        new ClientListenerInitializer(
            new AmpClientListenerFactory($serverSockets),
        )->initialize($container);
    }
}
