<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Registration;

use DateTimeInterface;
use PhpIrc\Irc\Channel\ChannelNameValidator;
use PhpIrc\Irc\Client\LusersResponseFactory;
use PhpIrc\Irc\Client\NicknameValidator;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Protocol\CaseMapping\CaseMapper;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;
use PhpIrc\Irc\Protocol\Target\ChannelTypes;
use PhpIrc\Irc\Transport\Connection;

final readonly class RegistrationWelcome
{
    public function __construct(
        private ServerConfig $config,
        private NumericResponseFactory $responses,
        private CaseMapper $caseMapper,
        private ChannelTypes $channelTypes,
        private LusersResponseFactory $lusersResponses,
    ) {}

    public function send(Connection $connection, string $nickname): void
    {
        $connection->send(
            $this->responses->create(
                code: ResponseCode::Welcome,
                target: $nickname,
                text: "Welcome to the {$this->config->networkName} Network, {$nickname}",
            ),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::YourHost,
                target: $nickname,
                text: "Your host is {$this->config->serverName->value}, running version {$this->config->softwareVersion}",
            ),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::Created,
                target: $nickname,
                text: 'This server was created ' . $this->config->startedAt->format(DateTimeInterface::ATOM),
            ),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::MyInfo,
                target: $nickname,
                parameters: [
                    $this->config->serverName->value,
                    $this->config->softwareVersion,
                    '-',
                    'ov',
                ],
            ),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::ISupport,
                target: $nickname,
                parameters: [
                    'CASEMAPPING=' . $this->caseMapper->name(),
                    'CHANMODES=,,,',
                    'CHANTYPES=' . $this->channelTypes->prefixes,
                    'CHANNELLEN=' . ChannelNameValidator::MAX_LENGTH,
                    'NICKLEN=' . NicknameValidator::MAX_LENGTH,
                    "NETWORK={$this->config->networkName}",
                    'PREFIX=(ov)@+',
                ],
            ),
        );

        array_map(
            $connection->send(...),
            $this->lusersResponses->createResponses($nickname),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::NoMotd,
                target: $nickname,
            ),
        );
    }
}
