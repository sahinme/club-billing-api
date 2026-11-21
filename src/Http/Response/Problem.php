<?php

declare(strict_types=1);

namespace ClubBilling\Http\Response;

/** An error response in the RFC 9457 "problem details" shape. */
final readonly class Problem
{
    /**
     * @param array<string, mixed> $extra extra members in the body, e.g. field errors
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public string $code,
        public string $detail,
        public array $extra = [],
        public array $headers = [],
    ) {}
}
