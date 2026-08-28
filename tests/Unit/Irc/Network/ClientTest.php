<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Network;

use PhpIrc\Irc\Network\Client;
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
            $client->setNickname('Grant');
        }

        if ($hasUsername) {
            $client->setUsername('grant');
        }

        if ($hasRealName) {
            $client->setRealName('Grant Burrows');
        }

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertFalse($client->registration->isComplete());
    }

    #[Test]
    public function it_completes_when_all_identity_fields_are_present(): void
    {
        $client = new Client();
        $client->setNickname('Grant');
        $client->setUsername('grant');
        $client->setRealName('Grant Burrows');

        $this->assertTrue($client->completeRegistrationIfReady());
        $this->assertTrue($client->registration->isComplete());
        $this->assertFalse($client->completeRegistrationIfReady());
    }

    #[Test]
    public function it_does_not_complete_while_capability_negotiation_is_active(): void
    {
        $client = new Client();
        $client->setNickname('Grant');
        $client->setUsername('grant');
        $client->setRealName('Grant Burrows');
        $client->registration->suspendForCapabilityNegotiation();

        $this->assertFalse($client->completeRegistrationIfReady());
        $this->assertFalse($client->registration->isComplete());
    }
}
