<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use LogicException;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Command\CommandContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class CommandContextTest extends TestCase
{
    #[Test]
    public function it_uses_the_clients_nickname_as_the_response_target(): void
    {
        $client = new Client();
        $client->setNickname('John');
        $context = new CommandContext(new RecordingConnection(), $client);

        $this->assertSame('John', $context->responseTarget());
    }

    #[Test]
    public function it_uses_an_asterisk_before_the_client_has_a_nickname(): void
    {
        $context = new CommandContext(
            new RecordingConnection(),
            new Client(),
        );

        $this->assertSame('*', $context->responseTarget());
    }

    #[Test]
    public function it_exposes_the_registered_actor_nickname(): void
    {
        $client = new Client();
        $client->setNickname('John');
        $context = new CommandContext(new RecordingConnection(), $client);

        $this->assertSame('John', $context->actorNickname());
    }

    #[Test]
    public function it_rejects_an_actor_without_a_nickname(): void
    {
        $context = new CommandContext(new RecordingConnection(), new Client());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A registered command actor must have a nickname.');

        $context->actorNickname();
    }
}
