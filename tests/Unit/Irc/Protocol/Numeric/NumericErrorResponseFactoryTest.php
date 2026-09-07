<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Protocol\Numeric;

use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NumericErrorResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_command_parameter_errors(): void
    {
        $factory = $this->factory();

        $this->assertResponse(
            $factory->needMoreParameters('John', 'JOIN'),
            '461',
            [
                'John',
                'JOIN',
                'Not enough parameters',
            ],
        );
        $this->assertResponse($factory->noNicknameGiven('John'), '431', ['John', 'No nickname given']);
        $this->assertResponse($factory->noOrigin('John'), '409', ['John', 'No origin specified']);
        $this->assertResponse($factory->noRecipient('John'), '411', ['John', 'No recipient given (PRIVMSG)']);
        $this->assertResponse($factory->noTextToSend('John'), '412', ['John', 'No text to send']);
    }

    #[Test]
    public function it_creates_named_target_errors(): void
    {
        $factory = $this->factory();

        $this->assertResponse(
            $factory->noSuchChannel('John', '#missing'),
            '403',
            [
                'John',
                '#missing',
                'No such channel',
            ],
        );
        $this->assertResponse(
            $factory->notOnChannel('John', '#php'),
            '442',
            [
                'John',
                '#php',
                "You're not on that channel",
            ],
        );
        $this->assertResponse(
            $factory->noSuchNickname('John', 'Missing'),
            '401',
            [
                'John',
                'Missing',
                'No such nick/channel',
            ],
        );
        $this->assertResponse(
            $factory->cannotSendToChannel('John', '#php'),
            '404',
            [
                'John',
                '#php',
                'Cannot send to channel',
            ],
        );
        $this->assertResponse(
            $factory->userNotInChannel('John', 'Jane', '#php'),
            '441',
            [
                'John',
                'Jane',
                '#php',
                "They aren't on that channel",
            ],
        );
        $this->assertResponse(
            $factory->channelOperatorPrivilegesNeeded('John', '#php'),
            '482',
            [
                'John',
                '#php',
                "You're not channel operator",
            ],
        );
    }

    #[Test]
    public function it_normalises_missing_named_values_to_a_wildcard(): void
    {
        $factory = $this->factory();

        $this->assertSame('*', $factory->noSuchChannel('John', '')->parameters[1]);
        $this->assertSame('*', $factory->noSuchNickname('John', '')->parameters[1]);
        $this->assertSame('*', $factory->cannotSendToChannel('John', '')->parameters[1]);
        $this->assertSame('*', $factory->invalidCapabilityCommand('John', '')->parameters[1]);
    }

    #[Test]
    public function it_creates_nickname_and_command_errors(): void
    {
        $factory = $this->factory();

        $this->assertResponse(
            $factory->erroneousNickname('John', 'bad nick'),
            '432',
            [
                'John',
                'bad nick',
                'Erroneous nickname',
            ],
        );
        $this->assertResponse(
            $factory->nicknameInUse('John', 'Jane'),
            '433',
            [
                'John',
                'Jane',
                'Nickname is already in use',
            ],
        );
        $this->assertResponse(
            $factory->invalidCapabilityCommand('John', 'NOPE'),
            '410',
            [
                'John',
                'NOPE',
                'Invalid CAP command',
            ],
        );
        $this->assertResponse(
            $factory->unknownMode('John', 'x'),
            '472',
            [
                'John',
                'x',
                'is unknown mode char to me',
            ],
        );
        $this->assertResponse(
            $factory->unknownCommand('John', 'NOPE'),
            '421',
            [
                'John',
                'NOPE',
                'Unknown command',
            ],
        );
    }

    #[Test]
    public function it_creates_client_state_errors(): void
    {
        $factory = $this->factory();

        $this->assertResponse($factory->alreadyRegistered('John'), '462', ['John', 'You may not reregister']);
        $this->assertResponse(
            $factory->usersDontMatch('John'),
            '502',
            [
                'John',
                'Cannot change mode for other users',
            ],
        );
        $this->assertResponse($factory->unknownUserModeFlag('John'), '501', ['John', 'Unknown MODE flag']);
        $this->assertResponse($factory->notRegistered('John'), '451', ['John', 'You have not registered']);
    }

    /** @param list<string> $parameters */
    private function assertResponse(Message $message, string $command, array $parameters): void
    {
        $this->assertSame([], $message->tags);
        $this->assertSame('irc.test', $message->source);
        $this->assertSame($command, $message->command);
        $this->assertSame($parameters, $message->parameters);
    }

    private function factory(): NumericErrorResponseFactory
    {
        return new NumericErrorResponseFactory(
            new NumericResponseFactory(new ServerName('irc.test')),
        );
    }
}
