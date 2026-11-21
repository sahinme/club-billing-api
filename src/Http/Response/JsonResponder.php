<?php

declare(strict_types=1);

namespace ClubBilling\Http\Response;

use ClubBilling\Infrastructure\Logging\RequestContext;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/** Builds every response the API sends: success bodies and error bodies. */
final readonly class JsonResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private RequestContext $context,
    ) {}

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function json(array $body, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->write($body, $status, 'application/json', $headers);
    }

    public function problem(Problem $problem): ResponseInterface
    {
        $body = [
            'type' => 'https://club-billing.example/problems/' . $problem->code,
            'title' => $this->responses->createResponse($problem->status)->getReasonPhrase(),
            'status' => $problem->status,
            'code' => $problem->code,
            'detail' => $problem->detail,
            // Lets support find the matching log lines for this exact request.
            'request_id' => $this->context->requestId,
        ] + $problem->extra;

        return $this->write($body, $problem->status, 'application/problem+json', $problem->headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function write(array $body, int $status, string $contentType, array $headers): ResponseInterface
    {
        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            // Billing data must never be cached by a browser or proxy.
            ->withHeader('Cache-Control', 'no-store');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
