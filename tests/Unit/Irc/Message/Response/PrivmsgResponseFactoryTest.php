<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Message\Response;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Response\AwayResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Message\MessageDeliveryFailure;
use PhpIrc\Irc\Message\MessageDeliveryFailureReason;
use PhpIrc\Irc\Message\Response\PrivmsgResponseFactory;
use PhpIrc\Irc\Protocol\ByteStringTruncator;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\MessageTextLimiter;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PrivmsgResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_missing_recipient_and_text_responses(): void
    {
        $factory = $this->factory();

        $missingRecipient = $factory->createMissingRecipientResponse('John');
        $missingText = $factory->createMissingTextResponse('John');

        $this->assertSame('411', $missingRecipient->command);
        $this->assertSame(['John', 'No recipient given (PRIVMSG)'], $missingRecipient->parameters);
        $this->assertSame('412', $missingText->command);
        $this->assertSame(['John', 'No text to send'], $missingText->parameters);
    }

    #[Test]
    public function it_creates_a_missing_nickname_delivery_response(): void
    {
        $response = $this->factory()->createDeliveryFailureResponse(
            'John',
            new MessageDeliveryFailure('Jane', MessageDeliveryFailureReason::NoSuchNickname),
        );

        $this->assertSame('401', $response->command);
        $this->assertSame(['John', 'Jane', 'No such nick/channel'], $response->parameters);
    }

    #[Test]
    public function it_creates_a_cannot_send_to_channel_delivery_response(): void
    {
        $response = $this->factory()->createDeliveryFailureResponse(
            'John',
            new MessageDeliveryFailure('#php', MessageDeliveryFailureReason::CannotSendToChannel),
        );

        $this->assertSame('404', $response->command);
        $this->assertSame(['John', '#php', 'Cannot send to channel'], $response->parameters);
    }

    #[Test]
    public function it_creates_an_away_recipient_response(): void
    {
        $recipient = new Client();
        $recipient->setNickname('Jane');
        $recipient->markAway('Gone for lunch');

        $response = $this->factory()->createRecipientAwayResponse('John', $recipient);

        $this->assertSame('301', $response->command);
        $this->assertSame(['John', 'Jane', 'Gone for lunch'], $response->parameters);
    }

    private function factory(): PrivmsgResponseFactory
    {
        $responses = new NumericResponseFactory(new ServerName('irc.test'));
        $strings = new ByteStringTruncator();

        return new PrivmsgResponseFactory(
            new NumericErrorResponseFactory(
                $responses,
                $strings,
            ),
            new AwayResponseFactory(
                responses: $responses,
                messageText: new MessageTextLimiter(
                    new MessageSize(new MessageEncoder()),
                    $strings,
                ),
            ),
        );
    }
}
