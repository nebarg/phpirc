<?php

declare(strict_types=1);

namespace Tests\Unit\Irc\Mode\Command;

use PhpIrc\Irc\Channel\ChannelBroadcaster;
use PhpIrc\Irc\Channel\ChannelModeResponseFactory;
use PhpIrc\Irc\Channel\ChannelPermissionResponseFactory;
use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Channel\Mode\ModeChangeParser;
use PhpIrc\Irc\Channel\Policy\ChannelAccessPolicy;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Command\CommandContext;
use PhpIrc\Irc\Config\ServerName;
use PhpIrc\Irc\Mode\ChannelModeHandler;
use PhpIrc\Irc\Mode\Command\ModeHandler;
use PhpIrc\Irc\Mode\UserModeHandler;
use PhpIrc\Irc\Protocol\CaseMapping\AsciiCaseMapper;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericErrorResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Protocol\Target\TargetClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Irc\Transport\RecordingConnection;
use Tests\TestCase;

final class ModeHandlerTest extends TestCase
{
    /** @return iterable<string, array{list<string>}> */
    public static function missingTargets(): iterable
    {
        yield 'missing target' => [[]];
        yield 'empty target' => [['']];
    }

    #[Test]
    public function it_handles_the_mode_command(): void
    {
        [$handler] = $this->handler();

        $this->assertSame('MODE', $handler->command());
    }

    /** @param list<string> $parameters */
    #[Test]
    #[DataProvider('missingTargets')]
    public function it_requires_a_target(array $parameters): void
    {
        [$handler] = $this->handler();
        $connection = new RecordingConnection();

        $handler->handle(
            new CommandContext($connection, new Client()),
            new Message(command: 'MODE', parameters: $parameters),
        );

        $this->assertResponse(
            connection: $connection,
            command: '461',
            parameters: ['*', 'MODE', 'Not enough parameters'],
        );
    }

    #[Test]
    public function it_routes_nickname_targets_to_the_user_mode_handler(): void
    {
        [$handler, $clients] = $this->handler();
        [$client, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'MODE', parameters: ['John']),
        );

        $this->assertResponse($connection, '221', ['John', '+']);
    }

    #[Test]
    public function it_routes_channel_targets_to_the_channel_mode_handler(): void
    {
        [$handler, $clients] = $this->handler();
        [$client, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'MODE', parameters: ['#missing']),
        );

        $this->assertResponse(
            connection: $connection,
            command: '403',
            parameters: ['John', '#missing', 'No such channel'],
        );
    }

    #[Test]
    public function it_routes_additional_supported_channel_types(): void
    {
        [$handler, $clients] = $this->handler(new ChannelTypes('#&'));
        [$client, $connection] = $this->register($clients, 'John');

        $handler->handle(
            new CommandContext($connection, $client),
            new Message(command: 'MODE', parameters: ['&missing']),
        );

        $this->assertResponse(
            connection: $connection,
            command: '403',
            parameters: ['John', '&missing', 'No such channel'],
        );
    }

    /** @return array{ModeHandler, ClientRegistry, ChannelRegistry} */
    private function handler(?ChannelTypes $channelTypes = null): array
    {
        $caseMapper = new AsciiCaseMapper();
        $clients = new ClientRegistry($caseMapper);
        $channels = new ChannelRegistry($caseMapper);
        $responses = new NumericResponseFactory(new ServerName('irc.test'));
        $errors = new NumericErrorResponseFactory($responses);

        return [
            new ModeHandler(
                channelModes: new ChannelModeHandler(
                    channels: $channels,
                    clients: $clients,
                    broadcaster: new ChannelBroadcaster($clients, $channels),
                    parser: new ModeChangeParser(),
                    errors: $errors,
                    modeResponses: new ChannelModeResponseFactory($responses),
                    channelAccess: new ChannelAccessPolicy(),
                    permissionResponses: new ChannelPermissionResponseFactory($errors),
                ),
                userModes: new UserModeHandler($clients, $responses, $errors),
                errors: $errors,
                targets: new TargetClassifier($channelTypes ?? new ChannelTypes()),
            ),
            $clients,
            $channels,
        ];
    }

    /** @return array{Client, RecordingConnection} */
    private function register(ClientRegistry $registry, string $nickname): array
    {
        $client = new Client();
        $client->setNickname($nickname);
        $client->setUsername(strtolower($nickname));
        $client->setRealName("{$nickname} Doe");
        $client->completeRegistrationIfReady();
        $connection = new RecordingConnection();
        $registry->register($client, $connection);
        $registry->claimNickname($client, $nickname);

        return [$client, $connection];
    }

    /** @param list<string> $parameters */
    private function assertResponse(
        RecordingConnection $connection,
        string $command,
        array $parameters,
    ): void {
        $this->assertCount(1, $connection->messages);
        $this->assertSame('irc.test', $connection->messages[0]->source);
        $this->assertSame($command, $connection->messages[0]->command);
        $this->assertSame($parameters, $connection->messages[0]->parameters);
    }
}
