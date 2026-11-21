<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

final readonly class IdempotencyRecord
{
    public function __construct(
        public string $requestFingerprint,
        public ?int $paymentId,
    ) {}
}
