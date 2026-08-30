<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientRegistryTest extends TestCase
{
    #[Test]
    public function it_claims_and_finds_a_nickname_case_insensitively(): void
    {
        $client = new Client();
        $registry = $this->registry();

        $claimed = $registry->claimNickname($client, 'John');

        $this->assertTrue($claimed);
        $this->assertSame('John', $client->nickname);
        $this->assertSame($client, $registry->findByNickname('john'));
        $this->assertSame($client, $registry->findByNickname('JOHN'));
    }

    #[Test]
    public function it_rejects_a_nickname_owned_by_another_client(): void
    {
        $owner = new Client();
        $other = new Client();
        $registry = $this->registry();
        $registry->claimNickname($owner, 'John');

        $claimed = $registry->claimNickname($other, 'JOHN');

        $this->assertFalse($claimed);
        $this->assertNull($other->nickname);
        $this->assertSame($owner, $registry->findByNickname('John'));
    }

    #[Test]
    public function it_treats_rfc1459_specific_equivalents_as_distinct(): void
    {
        $owner = new Client();
        $other = new Client();
        $registry = $this->registry();
        $registry->claimNickname($owner, '[John]\\^');

        $this->assertTrue($registry->claimNickname($other, '{JOHN}|~'));
        $this->assertSame($owner, $registry->findByNickname('[john]\\^'));
        $this->assertSame($other, $registry->findByNickname('{john}|~'));
    }

    #[Test]
    public function it_releases_the_previous_nickname_when_a_client_claims_a_new_one(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $registry->claimNickname($client, 'John');

        $claimed = $registry->claimNickname($client, 'OtherJohn');

        $this->assertTrue($claimed);
        $this->assertNull($registry->findByNickname('John'));
        $this->assertSame($client, $registry->findByNickname('OtherJohn'));
    }

    #[Test]
    public function it_releases_a_clients_nickname(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $registry->claimNickname($client, 'John');

        $registry->release($client);

        $this->assertNull($registry->findByNickname('John'));
    }

    #[Test]
    public function it_can_release_a_client_without_a_nickname(): void
    {
        $registry = $this->registry();

        $registry->release(new Client());

        $this->assertNull($registry->findByNickname('anything'));
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry(new AsciiCaseMapper());
    }
}
