<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Channel;

use LogicException;
use PhpIrc\Irc\Channel\Channel;
use PhpIrc\Irc\Channel\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\Policy\ChannelPermission;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelPermissionResponseFactoryTest extends TestCase
{
    #[Test]
    public function topic_changes_return_not_on_channel_for_a_non_member(): void
    {
        $response = $this->factory()->createTopicChangeDeniedResponse(
            ChannelPermission::NotMember,
            'John',
            new Channel('#php'),
        );

        $this->assertSame('442', $response->command);
        $this->assertSame(['John', '#php', "You're not on that channel"], $response->parameters);
    }

    #[Test]
    public function topic_changes_return_operator_privileges_needed_for_an_unprivileged_member(): void
    {
        $response = $this->factory()->createTopicChangeDeniedResponse(
            ChannelPermission::InsufficientPrivileges,
            'John',
            new Channel('#php'),
        );

        $this->assertSame('482', $response->command);
        $this->assertSame(['John', '#php', "You're not channel operator"], $response->parameters);
    }

    #[Test]
    public function mode_changes_return_operator_privileges_needed_for_every_denied_permission(): void
    {
        foreach ([ChannelPermission::NotMember, ChannelPermission::InsufficientPrivileges] as $permission) {
            $response = $this->factory()->createModeChangeDeniedResponse(
                $permission,
                'John',
                new Channel('#php'),
            );

            $this->assertSame('482', $response->command);
            $this->assertSame(['John', '#php', "You're not channel operator"], $response->parameters);
        }
    }

    #[Test]
    public function topic_responses_reject_an_allowed_permission(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An allowed channel permission has no error response.');

        $this->factory()->createTopicChangeDeniedResponse(
            ChannelPermission::Allowed,
            'John',
            new Channel('#php'),
        );
    }

    #[Test]
    public function mode_responses_reject_an_allowed_permission(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An allowed channel permission has no error response.');

        $this->factory()->createModeChangeDeniedResponse(
            ChannelPermission::Allowed,
            'John',
            new Channel('#php'),
        );
    }

    private function factory(): ChannelPermissionResponseFactory
    {
        return new ChannelPermissionResponseFactory(
            new NumericErrorResponseFactory(
                new NumericResponseFactory(new ServerName('irc.test')),
            ),
        );
    }
}
