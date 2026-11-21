<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use ClubBilling\Domain\Money;

final readonly class ChargeRequest
{
    public function __construct(
        public Money $amount,
        /** A token from the provider's card form, e.g. "pm_card_ok". We never see or store card numbers. */
        public string $paymentMethod,
        public string $idempotencyKey,
    ) {}
}
