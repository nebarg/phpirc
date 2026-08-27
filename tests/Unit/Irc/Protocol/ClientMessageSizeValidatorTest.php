<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol;

use Override;
use PhpIrc\Irc\Protocol\ClientMessageSizeValidator;
use PhpIrc\Irc\Protocol\InputTooLongException;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

final class ClientMessageSizeValidatorTest extends IntegrationTestCase
{
    private ClientMessageSizeValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new ClientMessageSizeValidator();
    }

    #[Test]
    public function it_accepts_tag_data_at_the_byte_limit(): void
    {
        $prefix = 'ourtag=';
        $tagData = $prefix
        . str_repeat(
            'a',
            ClientMessageSizeValidator::MAX_TAG_BYTES - strlen($prefix),
        );

        $this->assertSame(ClientMessageSizeValidator::MAX_TAG_BYTES, strlen($tagData));

        $this->validator->validate("@{$tagData} PING :token");
    }

    #[Test]
    public function it_rejects_tag_data_above_the_byte_limit(): void
    {
        $tagData = str_repeat('a', ClientMessageSizeValidator::MAX_TAG_BYTES + 1);

        $this->assertSame(ClientMessageSizeValidator::MAX_TAG_BYTES + 1, strlen($tagData));
        $this->expectException(InputTooLongException::class);

        $this->validator->validate("@{$tagData} PING :token");
    }

    #[Test]
    public function it_accepts_an_untagged_main_section_at_the_byte_limit(): void
    {
        $line = $this->mainSectionWithLength(ClientMessageSizeValidator::MAX_MAIN_BYTES);

        $this->assertSame(ClientMessageSizeValidator::MAX_MAIN_BYTES, strlen($line));

        $this->validator->validate($line);
    }

    #[Test]
    public function it_rejects_an_untagged_main_section_above_the_byte_limit(): void
    {
        $line = $this->mainSectionWithLength(ClientMessageSizeValidator::MAX_MAIN_BYTES + 1);

        $this->assertSame(ClientMessageSizeValidator::MAX_MAIN_BYTES + 1, strlen($line));
        $this->expectException(InputTooLongException::class);

        $this->validator->validate($line);
    }

    #[Test]
    public function it_accepts_a_tagged_main_section_at_the_byte_limit(): void
    {
        $main = $this->mainSectionWithLength(ClientMessageSizeValidator::MAX_MAIN_BYTES);

        $this->assertSame(ClientMessageSizeValidator::MAX_MAIN_BYTES, strlen($main));

        $this->validator->validate("@ourtag=value {$main}");
    }

    #[Test]
    public function it_rejects_a_tagged_main_section_above_the_byte_limit(): void
    {
        $main = $this->mainSectionWithLength(ClientMessageSizeValidator::MAX_MAIN_BYTES + 1);

        $this->assertSame(ClientMessageSizeValidator::MAX_MAIN_BYTES + 1, strlen($main));
        $this->expectException(InputTooLongException::class);

        $this->validator->validate("@ourtag=value {$main}");
    }

    #[Test]
    public function it_accepts_a_short_line_without_a_component_separator(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('COMMAND');
    }

    #[Test]
    public function it_leaves_short_malformed_tag_sections_to_the_parser(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('@ourtag=value');
    }

    private function mainSectionWithLength(int $length): string
    {
        $prefix = 'PRIVMSG #channel :';
        $contentLength = $length - strlen($prefix);
        assert($contentLength >= 0, 'The requested line must be at least as long as its prefix.');

        return $prefix . str_repeat('a', $contentLength);
    }
}
