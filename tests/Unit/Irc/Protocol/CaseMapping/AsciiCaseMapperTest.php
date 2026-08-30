<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol\CaseMapping;

use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AsciiCaseMapperTest extends TestCase
{
    #[Test]
    public function it_has_the_ascii_isupport_name(): void
    {
        $this->assertSame('ascii', new AsciiCaseMapper()->name());
    }

    #[Test]
    public function it_normalises_ascii_letters_to_lowercase(): void
    {
        $this->assertSame('john', new AsciiCaseMapper()->normalise('JoHn'));
    }

    #[Test]
    public function it_does_not_fold_rfc1459_specific_characters(): void
    {
        $mapper = new AsciiCaseMapper();

        $this->assertSame('[john]\\^', $mapper->normalise('[JoHn]\\^'));
        $this->assertSame('{john}|~', $mapper->normalise('{JoHn}|~'));
    }
}
