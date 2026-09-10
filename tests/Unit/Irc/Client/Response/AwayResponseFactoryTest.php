<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client\Response;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AwayResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_the_away_status_responses(): void
    {
        $factory = $this->factory();
        $client = new Client();
        $client->setNickname('Jane');
        $client->markAway('Gone for lunch');

        $away = $factory->createClientIsAwayResponse('John', $client);
        $present = $factory->createNoLongerAwayResponse('John');
        $markedAway = $factory->createMarkedAwayResponse('John');

        $this->assertSame('301', $away->command);
        $this->assertSame(['John', 'Jane', 'Gone for lunch'], $away->parameters);
        $this->assertSame('305', $present->command);
        $this->assertSame(['John', 'You are no longer marked as being away'], $present->parameters);
        $this->assertSame('306', $markedAway->command);
        $this->assertSame(['John', 'You have been marked as being away'], $markedAway->parameters);
    }

    #[Test]
    public function it_limits_away_replies_to_the_message_size_limit(): void
    {
        $serverName = new ServerName(str_repeat('s', ServerLimits::MAX_SERVER_NAME_BYTES));
        $client = new Client();
        $client->setNickname(str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES));
        $client->markAway(str_repeat('a', MessageSize::MAX_BYTES));
        $messageSize = new MessageSize(new MessageEncoder());

        $message = $this->factory($serverName)->createClientIsAwayResponse(
            str_repeat('t', ServerLimits::MAX_NICKNAME_BYTES),
            $client,
        );

        $this->assertSame(MessageSize::MAX_BYTES, $messageSize->inBytes($message));
        $this->assertLessThan(MessageSize::MAX_BYTES, strlen($message->parameter(2)));
    }

    #[Test]
    public function it_requires_an_away_client_with_a_nickname(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An away response requires a nicknamed, away client.');

        $this->factory()->createClientIsAwayResponse('John', new Client());
    }

    private function factory(?ServerName $serverName = null): AwayResponseFactory
    {
        $strings = new ByteStringTruncator();

        return new AwayResponseFactory(
            responses: new NumericResponseFactory($serverName ?? new ServerName('irc.test')),
            messageText: new MessageTextLimiter(
                new MessageSize(new MessageEncoder()),
                $strings,
            ),
        );
    }
}
