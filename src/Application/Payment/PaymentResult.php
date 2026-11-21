<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use ClubBilling\Domain\Payment\Payment;

final readonly class PaymentResult
{
    public function __construct(
        public Payment $payment,
        /** True when this answer comes from an earlier request with the same Idempotency-Key. */
        public bool $replayed,
    ) {}
}
