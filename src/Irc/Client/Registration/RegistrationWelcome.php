<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Registration;

use DateTimeInterface;
use PhpIrc\Irc\Client\Response\LusersResponseFactory;
use PhpIrc\Irc\Client\Response\MotdResponseFactory;
use PhpIrc\Irc\Config\ServerConfig;
use PhpIrc\Irc\Config\ServerLimits;
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
        private MotdResponseFactory $motdResponses,
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
                    'mntov',
                ],
            ),
        );

        $connection->send(
            $this->responses->create(
                code: ResponseCode::ISupport,
                target: $nickname,
                parameters: [
                    'CASEMAPPING=' . $this->caseMapper->name(),
                    'CHANMODES=,,,mnt',
                    'CHANTYPES=' . $this->channelTypes->prefixes,
                    'CHANNELLEN=' . ServerLimits::MAX_CHANNEL_NAME_BYTES,
                    'HOSTLEN=' . ServerLimits::MAX_HOSTNAME_BYTES,
                    'NICKLEN=' . ServerLimits::MAX_NICKNAME_BYTES,
                    "NETWORK={$this->config->networkName}",
                    'PREFIX=(ov)@+',
                    'TOPICLEN=' . ServerLimits::MAX_TOPIC_BYTES,
                    'USERLEN=' . ServerLimits::MAX_USERNAME_BYTES,
                ],
            ),
        );

        $connection->sendMany(
            $this->lusersResponses->createLusersResponses($nickname),
        );

        $connection->sendMany(
            $this->motdResponses->createMotdResponses($nickname),
        );
    }
}
