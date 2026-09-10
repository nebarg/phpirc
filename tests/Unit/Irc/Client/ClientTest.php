<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use InvalidArgumentException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\Mode\UserMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientTest extends TestCase
{
    /** @return iterable<string, array{bool, bool, bool}> */
    public static function incompleteIdentity(): iterable
    {
        yield 'missing everything' => [false, false, false];
        yield 'missing nickname' => [false, true, true];
        yield 'missing username' => [true, false, true];
        yield 'missing real name' => [true, true, false];
    }

    #[Test]
    #[DataProvider('incompleteIdentity')]
    public function it_does_not_complete_without_all_identity_fields(
        bool $hasNickname,
        bool $hasUsername,
        bool $hasRealName,
    ): void {
        $client = new Client();

        if ($hasNickname) {
            $client->setNickname('John');
        }

        if ($hasUsername) {
            $client->setUsername('john');
        }

        if ($hasRealName) {
            $client->setRealName('John Doe');
        }

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertFalse($client->registration->isComplete());
    }

    #[Test]
    public function it_completes_when_all_identity_fields_are_present(): void
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');

        $this->assertTrue($client->completeRegistrationIfReady());
        $this->assertTrue($client->registration->isComplete());
        $this->assertFalse($client->completeRegistrationIfReady());
    }

    #[Test]
    public function it_does_not_complete_while_capability_negotiation_is_active(): void
    {
        $client = new Client();
        $client->setNickname('John');
        $client->setUsername('john');
        $client->setRealName('John Doe');
        $client->registration->suspendForCapabilityNegotiation();

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertFalse($client->registration->isComplete());
    }

    #[Test]
    public function it_manages_user_modes_idempotently(): void
    {
        $client = new Client();

        $this->assertTrue($client->enableMode(UserMode::Invisible));
        $this->assertFalse($client->enableMode(UserMode::Invisible));
        $this->assertTrue($client->hasMode(UserMode::Invisible));
        $this->assertSame([UserMode::Invisible], $client->modes());
        $this->assertTrue($client->disableMode(UserMode::Invisible));
        $this->assertFalse($client->disableMode(UserMode::Invisible));
        $this->assertFalse($client->hasMode(UserMode::Invisible));
        $this->assertSame([], $client->modes());
    }

    #[Test]
    public function it_tracks_when_the_client_is_away_and_present(): void
    {
        $client = new Client();

        $client->markAway('Gone for lunch');

        $this->assertTrue($client->isAway());
        $this->assertSame('Gone for lunch', $client->awayMessage);

        $client->markPresent();

        $this->assertFalse($client->isAway());
        $this->assertNull($this->awayMessage($client));
    }

    #[Test]
    public function it_does_not_allow_an_empty_away_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Away message cannot be empty.');

        new Client()->markAway('');
    }

    private function awayMessage(Client $client): ?string
    {
        return $client->awayMessage;
    }
}
