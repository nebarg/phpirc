<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ClientRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_and_finds_a_clients_connection(): void
    {
        $client = new Client();
        $connection = new RecordingConnection();
        $registry = $this->registry();

        $registry->register($client, $connection);

        $this->assertSame($connection, $registry->connectionFor($client));
    }

    #[Test]
    public function it_distinguishes_clients_by_object_identity(): void
    {
        $first = new Client();
        $second = new Client();
        $firstConnection = new RecordingConnection();
        $secondConnection = new RecordingConnection();
        $registry = $this->registry();

        $registry->register($first, $firstConnection);
        $registry->register($second, $secondConnection);

        $this->assertSame($firstConnection, $registry->connectionFor($first));
        $this->assertSame($secondConnection, $registry->connectionFor($second));
    }

    #[Test]
    public function it_rejects_registering_the_same_client_twice(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $registry->register($client, new RecordingConnection());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Client is already registered.');

        $registry->register($client, new RecordingConnection());
    }

    #[Test]
    public function it_returns_null_for_an_unregistered_clients_connection(): void
    {
        $this->assertNull($this->registry()->connectionFor(new Client()));
    }

    #[Test]
    public function it_rejects_a_nickname_claim_from_an_unregistered_client(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Client must be registered before claiming a nickname.');

        $this->registry()->claimNickname(new Client(), 'John');
    }

    #[Test]
    public function it_claims_and_finds_a_nickname_case_insensitively(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $this->register($registry, $client);

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
        $this->register($registry, $owner);
        $this->register($registry, $other);
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
        $this->register($registry, $owner);
        $this->register($registry, $other);
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
        $this->register($registry, $client);
        $registry->claimNickname($client, 'John');

        $claimed = $registry->claimNickname($client, 'OtherJohn');

        $this->assertTrue($claimed);
        $this->assertNull($registry->findByNickname('John'));
        $this->assertSame($client, $registry->findByNickname('OtherJohn'));
    }

    #[Test]
    public function it_unregisters_a_client_and_releases_its_nickname(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $this->register($registry, $client);
        $registry->claimNickname($client, 'John');

        $registry->unregister($client);

        $this->assertNull($registry->connectionFor($client));
        $this->assertNull($registry->findByNickname('John'));
    }

    #[Test]
    public function it_can_unregister_a_client_without_a_nickname(): void
    {
        $client = new Client();
        $registry = $this->registry();
        $this->register($registry, $client);

        $registry->unregister($client);

        $this->assertNull($registry->connectionFor($client));
    }

    #[Test]
    public function unregistering_another_client_does_not_release_an_owned_nickname(): void
    {
        $owner = new Client();
        $other = new Client();
        $registry = $this->registry();
        $this->register($registry, $owner);
        $registry->claimNickname($owner, 'John');
        $other->setNickname('John');

        $registry->unregister($other);

        $this->assertSame($owner, $registry->findByNickname('John'));
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry(new AsciiCaseMapper());
    }

    private function register(ClientRegistry $registry, Client $client): void
    {
        $registry->register($client, new RecordingConnection());
    }
}
