<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Message\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Message\Command\NoticeHandler;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class NoticeHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function incompleteNotices(): iterable
    {
        yield 'missing target' => [[]];
        yield 'empty target' => [['']];
        yield 'missing text' => [['Jane']];
        yield 'empty text' => [['Jane', '']];
    }

    #[Test]
    public function it_handles_the_notice_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('NOTICE', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('incompleteNotices')]
    public function it_silently_ignores_an_incomplete_notice(array $parameters): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'NOTICE', parameters: $parameters),
        );

        $this->assertSame([], $johnConnection->messages);
    }

    #[Test]
    public function it_silently_ignores_an_unknown_target(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'NOTICE', parameters: ['Missing', 'Hello']),
        );

        $this->assertSame([], $johnConnection->messages);
    }

    #[Test]
    public function it_silently_ignores_a_notice_rejected_by_channel_modes(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'NOTICE', parameters: ['#PHP', 'Hello']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertSame([], $janeConnection->messages);
    }

    #[Test]
    public function it_silently_ignores_a_notice_before_registration(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients, registered: false);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'NOTICE', parameters: ['Jane', 'Hello']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertSame([], $janeConnection->messages);
    }

    #[Test]
    public function it_delivers_to_clients_channels_and_multiple_targets(): void
    {
        [$handler, $clients, $channels] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(
                command: 'NOTICE',
                parameters: ['#PHP,jane,Missing', 'Hello targets'],
            ),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertCount(2, $janeConnection->messages);
        $this->assertNotice($janeConnection, '#php', index: 0);
        $this->assertNotice($janeConnection, 'Jane', index: 1);
    }

    #[Test]
    public function it_does_not_reply_when_the_recipient_is_away(): void
    {
        [$handler, $clients] = $this->handler();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $jane->markAway('Gone for lunch');

        $handler->handle(
            new CommandContext($johnConnection, $john),
            new Message(command: 'NOTICE', parameters: ['Jane', 'Hello Jane']),
        );

        $this->assertSame([], $johnConnection->messages);
        $this->assertCount(1, $janeConnection->messages);
    }

    /** @return array{NoticeHandler, ClientRegistry, ChannelRegistry} */
    private function handler(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new NoticeHandler(new MessageDelivery(
                clients: $clients,
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients),
                targets: new TargetClassifier(new ChannelTypes()),
                channelAccess: new ChannelAccessPolicy(),
                messageText: new MessageTextLimiter(
                    new MessageSize(new MessageEncoder()),
                    new ByteStringTruncator(),
                ),
            )),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(
        string $nickname,
        ClientRegistry $clients,
        bool $registered = true,
    ): array {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);

        if ($registered) {
            $client->setUsername(strtolower($nickname));
            $client->setRealName("{$nickname} Doe");
            $client->completeRegistrationIfReady();
        }

        return [$client, $connection];
    }

    private function assertNotice(
        RecordingConnection $connection,
        string $target,
        int $index,
    ): void {
        $this->assertSame([], $connection->messages[$index]->tags);
        $this->assertSame('John', $connection->messages[$index]->source);
        $this->assertSame('NOTICE', $connection->messages[$index]->command);
        $this->assertSame([$target, 'Hello targets'], $connection->messages[$index]->parameters);
    }
}
