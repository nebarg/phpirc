<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Protocol\Target;

final readonly class TargetClassifier
{
    public function __construct(
        private ChannelTypes $channelTypes,
    ) {}

    public function classify(string $target): TargetType
    {
        return $this->channelTypes->isChannelTarget($target)
            ? TargetType::Channel
            : TargetType::Nickname;
    }
}
