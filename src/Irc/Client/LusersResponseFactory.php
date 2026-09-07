<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Client;

use PhpIrc\Irc\Channel\ChannelRegistry;
use PhpIrc\Irc\Protocol\Message;
use PhpIrc\Irc\Protocol\Numeric\NumericResponseFactory;
use PhpIrc\Irc\Protocol\Numeric\ResponseCode;

final readonly class LusersResponseFactory
{
    public function __construct(
        private ClientRegistry $clients,
        private ChannelRegistry $channels,
        private NumericResponseFactory $responses,
    ) {}

    /** @return list<Message> */
    public function createLusersResponses(string $target): array
    {
        $registeredClients = $this->clients->registeredCount();
        $responses = [
            $this->responses->create(
                code: ResponseCode::LuserClient,
                target: $target,
                text: "There are {$registeredClients} users and 0 invisible on 1 servers",
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
            text: "I have {$registeredClients} clients and 0 servers",
        );

        return $responses;
    }
}
