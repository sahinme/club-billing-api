<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Logging;

/**
 * Facts about the current request that every log line should carry.
 *
 * Mutable shared state is fine here because php-fpm builds a fresh container for every request.
 * In a long-running runtime (RoadRunner, Swoole, FrankenPHP worker mode) this would need a reset
 * between requests.
 */
final class RequestContext
{
    public private(set) ?string $requestId = null;

    public private(set) ?int $userId = null;

    public function startRequest(string $requestId): void
    {
        $this->requestId = $requestId;
        $this->userId = null;
    }

    public function authenticatedAs(int $userId): void
    {
        $this->userId = $userId;
    }
}
