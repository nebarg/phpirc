<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Client;

use PhpIrc\Irc\Client\Motd;
use PhpIrc\Irc\Client\MotdResponseFactory;
use PhpIrc\Irc\Config\ServerLimits;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\MessageEncoder;
use PhpIrc\Irc\Protocol\MessageSize;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MotdResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_no_motd_response_when_no_lines_are_configured(): void
    {
        $responses = $this->factory()->createMotdResponses('John');

        $this->assertCount(1, $responses);
        $this->assertSame('irc.test', $responses[0]->source);
        $this->assertSame('422', $responses[0]->command);
        $this->assertSame(['John', 'MOTD File is missing'], $responses[0]->parameters);
    }

    #[Test]
    public function it_creates_the_complete_motd_response_sequence(): void
    {
        $responses = $this->factory(new Motd([
            'Welcome to TestNet.',
            '',
            'Have fun!',
        ]))->createMotdResponses('John');

        $this->assertSame(['375', '372', '372', '372', '376'], array_column($responses, 'command'));
        $this->assertSame(['John', '- irc.test Message of the day -'], $responses[0]->parameters);
        $this->assertSame(['John', '- Welcome to TestNet.'], $responses[1]->parameters);
        $this->assertSame(['John', '- '], $responses[2]->parameters);
        $this->assertSame(['John', '- Have fun!'], $responses[3]->parameters);
        $this->assertSame(['John', 'End of /MOTD command.'], $responses[4]->parameters);
    }

    #[Test]
    public function it_keeps_the_largest_valid_motd_line_within_the_message_size_limit(): void
    {
        $maximumBytes = ServerLimits::MAX_MOTD_LINE_BYTES;
        assert($maximumBytes > 0, 'MOTD line limit must be positive.');

        $serverName = new ServerName(str_repeat('s', ServerLimits::MAX_SERVER_NAME_BYTES));
        $responses = $this->factory(
            motd: new Motd([str_repeat('x', $maximumBytes)]),
            serverName: $serverName,
        )->createMotdResponses(str_repeat('n', ServerLimits::MAX_NICKNAME_BYTES));
        $messageSize = new MessageSize(new MessageEncoder());

        foreach ($responses as $response) {
            $this->assertTrue($messageSize->fits($response));
        }
    }

    private function factory(
        ?Motd $motd = null,
        ?ServerName $serverName = null,
    ): MotdResponseFactory {
        $serverName ??= new ServerName('irc.test');

        return new MotdResponseFactory(
            $serverName,
            $motd ?? new Motd(),
            new NumericResponseFactory($serverName),
        );
    }
}
