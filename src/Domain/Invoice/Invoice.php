<?php

declare(strict_types=1);

namespace ClubBilling\Domain\Invoice;

use ClubBilling\Domain\BusinessRuleViolation;
use ClubBilling\Domain\Money;

/**
 * A bill a gym member has to pay, for example a monthly membership fee.
 * Partial payments are allowed; the invoice becomes "paid" when nothing is outstanding.
 *
 * $paid and $status can be read from outside but only changed through applyPayment(),
 * so the billing rules cannot be skipped (PHP 8.4 asymmetric visibility).
 */
final class Invoice
{
    public function __construct(
        public readonly int $id,
        public readonly int $clubId,
        public readonly int $memberId,
        public readonly string $description,
        public readonly Money $total,
        public private(set) Money $paid,
        public private(set) InvoiceStatus $status,
    ) {}

    public function outstanding(): Money
    {
        return $this->total->subtract($this->paid);
    }

    /**
     * @throws BusinessRuleViolation when this invoice cannot take a payment of $amount right now
     */
    public function assertCanAcceptPayment(Money $amount): void
    {
        if ($this->status !== InvoiceStatus::Open) {
            throw new BusinessRuleViolation('invoice_not_open', sprintf('Invoice is %s and cannot take payments.', $this->status->value));
        }
        if (!$amount->hasSameCurrencyAs($this->total)) {
            throw new BusinessRuleViolation('currency_mismatch', sprintf('Invoice is in %s.', $this->total->currency));
        }
        if ($amount->isGreaterThan($this->outstanding())) {
            throw new BusinessRuleViolation(
                'amount_exceeds_outstanding',
                sprintf('Amount is more than the outstanding %d %s.', $this->outstanding()->amount, $this->total->currency),
            );
        }
    }

    public function applyPayment(Money $amount): void
    {
        $this->assertCanAcceptPayment($amount);

        $this->paid = $this->paid->add($amount);
        if ($this->outstanding()->isZero()) {
            $this->status = InvoiceStatus::Paid;
        }
    }
}
