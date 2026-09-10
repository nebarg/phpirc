<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Message;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\ChannelMode;
use PhpIrc\Irc\Channel\Mode\MembershipMode;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Message\MessageDelivery;
use PhpIrc\Irc\Message\MessageDeliveryFailureReason;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class MessageDeliveryTest extends TestCase
{
    #[Test]
    public function it_delivers_to_a_client_using_their_canonical_nickname(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $report = $delivery->deliver(
            sender: $john,
            command: 'NOTICE',
            targets: 'jAnE',
            text: 'Hello Jane',
        );

        $this->assertSame([], $report->failures);
        $this->assertSame([], $report->awayRecipients);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'NOTICE',
            target: 'Jane',
            text: 'Hello Jane',
        );
    }

    #[Test]
    public function it_reports_an_away_recipient_after_delivering_a_direct_message(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $jane->markAway('Gone for lunch');

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: 'Jane',
            text: 'Hello Jane',
        );

        $this->assertSame([], $report->failures);
        $this->assertSame([$jane], $report->awayRecipients);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: 'Jane',
            text: 'Hello Jane',
        );
    }

    #[Test]
    public function it_does_not_report_away_channel_members(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [$jane] = $this->connectedClient('Jane', $clients);
        $jane->markAway('Gone for lunch');
        $channels->join('#php', $john);
        $channels->join('#php', $jane);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#php',
            text: 'Hello channel',
        );

        $this->assertSame([], $report->awayRecipients);
    }

    #[Test]
    public function it_delivers_to_channel_members_except_the_sender(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$john, $johnConnection] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#PHP', $john);
        $channels->join('#php', $jane);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#php',
            text: 'Hello channel',
        );

        $this->assertSame([], $report->failures);
        $this->assertSame([], $report->awayRecipients);
        $this->assertSame([], $johnConnection->messages);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: '#PHP',
            text: 'Hello channel',
        );
    }

    #[Test]
    public function it_returns_missing_targets_after_delivering_valid_targets(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: 'Missing,Jane,',
            text: 'Hello targets',
        );

        $this->assertCount(2, $report->failures);
        $this->assertSame('Missing', $report->failures[0]->target);
        $this->assertSame(MessageDeliveryFailureReason::NoSuchNickname, $report->failures[0]->reason);
        $this->assertSame('', $report->failures[1]->target);
        $this->assertSame(MessageDeliveryFailureReason::NoSuchNickname, $report->failures[1]->reason);
        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: 'Jane',
            text: 'Hello targets',
        );
    }

    #[Test]
    public function it_does_not_probe_the_nickname_registry_for_a_channel_target(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $invalidNicknameConnection] = $this->connectedClient('#missing', $clients);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#missing',
            text: 'Hello target',
        );

        $this->assertCount(1, $report->failures);
        $this->assertSame('#missing', $report->failures[0]->target);
        $this->assertSame(MessageDeliveryFailureReason::CannotSendToChannel, $report->failures[0]->reason);
        $this->assertSame([], $invalidNicknameConnection->messages);
    }

    #[Test]
    public function it_rejects_an_outsider_when_no_external_messages_mode_is_enabled(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channels->join('#PHP', $jane);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#php',
            text: 'Hello channel',
        );

        $this->assertCount(1, $report->failures);
        $this->assertSame('#PHP', $report->failures[0]->target);
        $this->assertSame(MessageDeliveryFailureReason::CannotSendToChannel, $report->failures[0]->reason);
        $this->assertSame([], $janeConnection->messages);
    }

    #[Test]
    public function it_allows_an_outsider_when_no_external_messages_mode_is_disabled(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [$jane, $janeConnection] = $this->connectedClient('Jane', $clients);
        $channel = $channels->join('#php', $jane);
        $channel->disableMode(ChannelMode::NoExternalMessages);

        $report = $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: '#PHP',
            text: 'Hello channel',
        );

        $this->assertSame([], $report->failures);
        $this->assertDeliveredMessage($janeConnection, 'PRIVMSG', '#php', 'Hello channel');
    }

    #[Test]
    public function moderated_channels_only_accept_messages_from_privileged_members(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$operator] = $this->connectedClient('John', $clients);
        [$member] = $this->connectedClient('Jane', $clients);
        [$voiced] = $this->connectedClient('Fred', $clients);
        [$recipient, $recipientConnection] = $this->connectedClient('Mary', $clients);
        $channel = $channels->join('#php', $operator);
        $channels->join('#php', $member);
        $voicedMembership = $channels->join('#php', $voiced)->membershipFor($voiced);
        $channels->join('#php', $recipient);
        $this->assertNotNull($voicedMembership);
        $voicedMembership->grant(MembershipMode::Voice);
        $channel->enableMode(ChannelMode::Moderated);

        $failedReport = $delivery->deliver($member, 'PRIVMSG', '#php', 'Blocked');
        $successfulReport = $delivery->deliver($voiced, 'PRIVMSG', '#php', 'Allowed');

        $this->assertCount(1, $failedReport->failures);
        $this->assertSame(MessageDeliveryFailureReason::CannotSendToChannel, $failedReport->failures[0]->reason);
        $this->assertSame([], $successfulReport->failures);
        $this->assertDeliveredMessage($recipientConnection, 'PRIVMSG', '#php', 'Allowed', source: 'Fred');
    }

    #[Test]
    public function it_preserves_irc_formatting_and_ctcp_bytes(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$john] = $this->connectedClient('John', $clients);
        [, $janeConnection] = $this->connectedClient('Jane', $clients);
        $text = "\x01ACTION \x02waves\x02 in \x0304red\x0F\x01";

        $delivery->deliver(
            sender: $john,
            command: 'PRIVMSG',
            targets: 'Jane',
            text: $text,
        );

        $this->assertDeliveredMessage(
            $janeConnection,
            command: 'PRIVMSG',
            target: 'Jane',
            text: $text,
        );
    }

    #[Test]
    public function it_limits_notices_delivered_to_clients_to_the_message_size_limit(): void
    {
        [$delivery, $clients] = $this->delivery();
        [$sender] = $this->connectedClient(str_repeat('s', ServerLimits::MAX_NICKNAME_BYTES), $clients);
        [, $recipientConnection] = $this->connectedClient(
            str_repeat('r', ServerLimits::MAX_NICKNAME_BYTES),
            $clients,
        );
        $text = str_repeat('Long notice message ', 30);

        $report = $delivery->deliver(
            sender: $sender,
            command: 'NOTICE',
            targets: str_repeat('r', ServerLimits::MAX_NICKNAME_BYTES),
            text: $text,
        );

        $this->assertSame([], $report->failures);
        $this->assertCount(1, $recipientConnection->messages);
        $this->assertSame(MessageSize::MAX_BYTES, $this->messageSize()->inBytes($recipientConnection->messages[0]));
        $this->assertLessThan(strlen($text), strlen($recipientConnection->messages[0]->parameter(1)));
    }

    #[Test]
    public function it_limits_privmsgs_delivered_to_channels_to_the_message_size_limit(): void
    {
        [$delivery, $clients, $channels] = $this->delivery();
        [$sender] = $this->connectedClient(str_repeat('s', ServerLimits::MAX_NICKNAME_BYTES), $clients);
        [$recipient, $recipientConnection] = $this->connectedClient('Jane', $clients);
        $channelName = '#' . str_repeat('c', ServerLimits::MAX_CHANNEL_NAME_BYTES - 1);
        $channels->join($channelName, $sender);
        $channels->join($channelName, $recipient);
        $text = str_repeat('Long channel message ', 30);

        $report = $delivery->deliver(
            sender: $sender,
            command: 'PRIVMSG',
            targets: $channelName,
            text: $text,
        );

        $this->assertSame([], $report->failures);
        $this->assertCount(1, $recipientConnection->messages);
        $this->assertSame(MessageSize::MAX_BYTES, $this->messageSize()->inBytes($recipientConnection->messages[0]));
        $this->assertLessThan(strlen($text), strlen($recipientConnection->messages[0]->parameter(1)));
    }

    /** @return array{MessageDelivery, ClientRegistry, ChannelRegistry} */
    private function delivery(): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);

        return [
            new MessageDelivery(
                clients: $clients,
                channels: $channels,
                broadcaster: new ChannelBroadcaster($clients),
                targets: new TargetClassifier(new ChannelTypes()),
                channelAccess: new ChannelAccessPolicy(),
                messageText: new MessageTextLimiter($this->messageSize(), new ByteStringTruncator()),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function connectedClient(string $nickname, ClientRegistry $clients): array
    {
        $client = new Client();
        $connection = new RecordingConnection();
        $clients->register($client, $connection);
        $clients->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    private function assertDeliveredMessage(
        RecordingConnection $connection,
        string $command,
        string $target,
        string $text,
        string $source = 'John',
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame([], $connection->messages[0]->tags);
        $this->assertSame($source, $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame([$target, $text], $connection->messages[0]->parameters);
    }

    private function messageSize(): MessageSize
    {
        return new MessageSize(new MessageEncoder());
    }
}
