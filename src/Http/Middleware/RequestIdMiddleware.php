<?php

declare(strict_types=1);

namespace ClubBilling\Http\Middleware;

use ClubBilling\Infrastructure\Logging\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Gives every request an id that appears in every log line and in the X-Request-Id response header.
 * A user reporting a problem can send us that id and we find the exact log lines.
 */
final readonly class RequestIdMiddleware implements MiddlewareInterface
{
    public function __construct(private RequestContext $context) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = self::incomingIdOrNew($request->getHeaderLine('X-Request-Id'));
        $this->context->startRequest($requestId);

        return $handler->handle($request)->withHeader('X-Request-Id', $requestId);
    }

    private static function incomingIdOrNew(string $incoming): string
    {
        // Reuse the id from an upstream proxy/gateway, but only if it is safe to put in logs.
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1 ? $incoming : Uuid::uuid4()->toString();
    }
}
