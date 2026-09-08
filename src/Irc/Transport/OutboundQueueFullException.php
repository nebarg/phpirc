<?php

declare(strict_types=1);

namespace PhpIrc\Irc\Transport;

use RuntimeException;

final class OutboundQueueFullException extends RuntimeException {}
