<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client\Response;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Client\Client;
use PhpIrc\Irc\Client\ClientRegistry;
use PhpIrc\Irc\Client\Mode\UserMode;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;
use PhpIrc\Irc\Transport\ConnectionStatistics;

final readonly class LusersResponseFactory
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private NumericResponseFactory $responses,
        private ConnectionStatistics $statistics,
    ) {}

    /** @return list<Message> */
    public function createLusersResponses(string $target): array
    {
        $registeredClients = $this->clients->registeredClients();
        $invisibleClients = count(array_filter(
            $registeredClients,
            static fn (Client $client): bool => $client->hasMode(UserMode::Invisible),
        ));
        $visibleClients = count($registeredClients) - $invisibleClients;
        $responses = [
            $this->responses->create(
                code: ResponseCode::LuserClient,
                target: $target,
                text: "There are {$visibleClients} users and {$invisibleClients} invisible on 1 servers",
            ),
        ];

        $unregisteredClients = $this->clients->unregisteredCount();

        if ($unregisteredClients > 0) {
            $responses[] = $this->responses->create(
                code: ResponseCode::LuserUnknown,
                target: $target,
                parameters: [(string) $unregisteredClients],
            );
        }

        $channels = $this->channels->count();

        if ($channels > 0) {
            $responses[] = $this->responses->create(
                code: ResponseCode::LuserChannels,
                target: $target,
                parameters: [(string) $channels],
            );
        }

        $responses[] = $this->responses->create(
            code: ResponseCode::LuserMe,
            target: $target,
            text: 'I have ' . count($registeredClients) . ' clients and 0 servers',
        );

        $registeredClientCount = count($registeredClients);
        $peakRegisteredClients = max(
            $registeredClientCount,
            $this->statistics->peakRegisteredClients,
        );
        $responses[] = $this->createLocalUsersResponse(
            $target,
            $registeredClientCount,
            $peakRegisteredClients,
        );
        $responses[] = $this->createGlobalUsersResponse(
            $target,
            $registeredClientCount,
            $peakRegisteredClients,
        );
        $responses[] = $this->createConnectionStatisticsResponse(
            $target,
            $peakRegisteredClients,
        );

        return $responses;
    }

    private function createLocalUsersResponse(
        string $target,
        int $current,
        int $peak,
    ): Message {
        return $this->responses->create(
            code: ResponseCode::LocalUsers,
            target: $target,
            parameters: [
                (string) $current,
                (string) $peak,
            ],
            text: "Current local users {$current}, max {$peak}",
        );
    }

    private function createGlobalUsersResponse(
        string $target,
        int $current,
        int $peak,
    ): Message {
        return $this->responses->create(
            code: ResponseCode::GlobalUsers,
            target: $target,
            parameters: [
                (string) $current,
                (string) $peak,
            ],
            text: "Current global users {$current}, max {$peak}",
        );
    }

    private function createConnectionStatisticsResponse(
        string $target,
        int $peakRegisteredClients,
    ): Message {
        $connectedClientCount = $this->clients->connectedCount();
        $peakConnections = max(
            $connectedClientCount,
            $this->statistics->peakConnections,
        );
        $connectionsReceived = max(
            $connectedClientCount,
            $this->statistics->connectionsReceived,
        );
        $connectionStats = sprintf(
            'Highest connection count: %d (%d clients) (%d connections received)',
            $peakConnections,
            $peakRegisteredClients,
            $connectionsReceived,
        );

        return $this->responses->create(
            code: ResponseCode::StatsConnections,
            target: $target,
            text: $connectionStats,
        );
    }
}
