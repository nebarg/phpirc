<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Command;

use PhpIrc\Irc\Command\NumericResponseFactory;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\ResponseCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NumericResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_server_numeric_with_its_default_text(): void
    {
        $response = $this->factory()->create(
            code: ResponseCode::NoNicknameGiven,
        );

        $this->assertSame([], $response->tags);
        $this->assertSame('irc.test', $response->source);
        $this->assertSame('431', $response->command);
        $this->assertSame(
            ['*', 'No nickname given'],
            $response->parameters,
        );
    }

    #[Test]
    public function it_includes_the_target_and_numeric_parameters(): void
    {
        $response = $this->factory()->create(
            code: ResponseCode::UnknownCommand,
            target: 'Grant',
            parameters: ['WHATEVER'],
        );

        $this->assertSame(
            ['Grant', 'WHATEVER', 'Unknown command'],
            $response->parameters,
        );
    }

    #[Test]
    public function it_accepts_dynamic_text_for_a_numeric_without_a_default(): void
    {
        $response = $this->factory()->create(
            code: ResponseCode::Welcome,
            target: 'Grant',
            text: 'Welcome to TestNet',
        );

        $this->assertSame('001', $response->command);
        $this->assertSame(
            ['Grant', 'Welcome to TestNet'],
            $response->parameters,
        );
    }

    private function factory(): NumericResponseFactory
    {
        return new NumericResponseFactory(new ServerName('irc.test'));
    }
}
