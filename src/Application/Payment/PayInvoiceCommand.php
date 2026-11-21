<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use ClubBilling\Domain\Money;
use ClubBilling\Domain\User\User;

/** A validated "pay this invoice" request. Built by the HTTP layer, so the service never sees raw input. */
final readonly class PayInvoiceCommand
{
    public function __construct(
        public User $actor,
        public int $invoiceId,
        public Money $amount,
        public string $paymentMethod,
        public string $idempotencyKey,
    ) {}

    /** Identifies the request body, so a reused Idempotency-Key with a different body can be detected. */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [$this->invoiceId, $this->amount->amount, $this->amount->currency, $this->paymentMethod]));
    }
}
