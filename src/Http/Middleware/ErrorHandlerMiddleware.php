<?php

declare(strict_types=1);

namespace ClubBilling\Http\Middleware;

use ClubBilling\Application\Auth\AccessDenied;
use ClubBilling\Application\Auth\AuthenticationFailed;
use ClubBilling\Application\Conflict;
use ClubBilling\Application\ResourceNotFound;
use ClubBilling\Domain\BusinessRuleViolation;
use ClubBilling\Http\Request\ValidationException;
use ClubBilling\Http\Response\JsonResponder;
use ClubBilling\Http\Response\Problem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * The single place where exceptions become HTTP responses.
 *
 * Expected errors (4xx) carry a message written for the client; we return it and log one short line.
 * Anything else is a bug or an outage (5xx): the client gets a generic message plus the request id,
 * the log gets the full exception with stack trace. Internal details never reach the client.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JsonResponder $responder,
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $error) {
            $problem = self::toProblem($error);
            $this->log($problem, $error, $request);

            return $this->responder->problem($problem);
        }
    }

    private static function toProblem(Throwable $error): Problem
    {
        return match (true) {
            $error instanceof ValidationException => new Problem(422, 'validation_failed', 'The request is not valid.', extra: ['errors' => $error->errors]),
            $error instanceof AuthenticationFailed => new Problem(401, 'unauthenticated', $error->getMessage(), headers: ['WWW-Authenticate' => 'Bearer']),
            $error instanceof AccessDenied => new Problem(403, 'forbidden', $error->getMessage()),
            $error instanceof ResourceNotFound => new Problem(404, 'not_found', $error->getMessage()),
            $error instanceof HttpNotFoundException => new Problem(404, 'not_found', 'Route not found.'),
            $error instanceof HttpMethodNotAllowedException => new Problem(405, 'method_not_allowed', 'Method not allowed.', headers: ['Allow' => implode(', ', $error->getAllowedMethods())]),
            $error instanceof Conflict => new Problem(409, $error->errorCode, $error->getMessage()),
            $error instanceof BusinessRuleViolation => new Problem(422, $error->errorCode, $error->getMessage()),
            default => new Problem(500, 'internal_error', 'Something went wrong on our side. Please contact support and quote the request_id.'),
        };
    }

    private function log(Problem $problem, Throwable $error, ServerRequestInterface $request): void
    {
        $context = [
            'status' => $problem->status,
            'error_code' => $problem->code,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ];

        if ($problem->status >= 500) {
            // Full exception for us. Not the request body or headers: they can contain passwords, tokens or personal data.
            $this->logger->error('request.failed', $context + ['exception' => $error]);
        } else {
            $this->logger->info('request.rejected', $context);
        }
    }
}
