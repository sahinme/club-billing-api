<?php

declare(strict_types=1);

namespace ClubBilling\Domain\Payment;

use ClubBilling\Domain\Money;
use DateTimeImmutable;
use LogicException;

/**
 * One attempt to collect money for an invoice.
 * Life cycle: pending → succeeded | failed. A finished payment never changes again.
 */
final class Payment
{
    public function __construct(
        public readonly int $id,
        public readonly int $invoiceId,
        public readonly int $clubId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $createdAt,
        public private(set) PaymentStatus $status = PaymentStatus::Pending,
        public private(set) ?string $gatewayReference = null,
        public private(set) ?string $failureReason = null,
    ) {}

    public function markSucceeded(string $gatewayReference): void
    {
        $this->assertPending();
        $this->status = PaymentStatus::Succeeded;
        $this->gatewayReference = $gatewayReference;
    }

    public function markFailed(string $gatewayReference, string $reason): void
    {
        $this->assertPending();
        $this->status = PaymentStatus::Failed;
        $this->gatewayReference = $gatewayReference;
        $this->failureReason = $reason;
    }

    private function assertPending(): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw new LogicException(sprintf('Payment %d is already %s.', $this->id, $this->status->value));
        }
    }
}
