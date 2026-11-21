<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Unit;

use ClubBilling\Domain\BusinessRuleViolation;
use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\Invoice\InvoiceStatus;
use ClubBilling\Domain\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function testPartialPaymentKeepsInvoiceOpen(): void
    {
        $invoice = self::openInvoice(total: 4999);

        $invoice->applyPayment(new Money(2000, 'EUR'));

        self::assertSame(InvoiceStatus::Open, $invoice->status);
        self::assertSame(2999, $invoice->outstanding()->amount);
    }

    public function testPayingTheRestMarksInvoicePaid(): void
    {
        $invoice = self::openInvoice(total: 4999);

        $invoice->applyPayment(new Money(2000, 'EUR'));
        $invoice->applyPayment(new Money(2999, 'EUR'));

        self::assertSame(InvoiceStatus::Paid, $invoice->status);
        self::assertTrue($invoice->outstanding()->isZero());
    }

    public function testRejectsMoreThanOutstanding(): void
    {
        $invoice = self::openInvoice(total: 4999);

        $this->expectExceptionObject(new BusinessRuleViolation('amount_exceeds_outstanding', 'Amount is more than the outstanding 4999 EUR.'));

        $invoice->applyPayment(new Money(5000, 'EUR'));
    }

    public function testRejectsOtherCurrency(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        self::openInvoice(total: 4999)->applyPayment(new Money(100, 'USD'));
    }

    public function testRejectsPaymentOnPaidInvoice(): void
    {
        $invoice = self::openInvoice(total: 100);
        $invoice->applyPayment(new Money(100, 'EUR'));

        $this->expectException(BusinessRuleViolation::class);

        $invoice->applyPayment(new Money(1, 'EUR'));
    }

    private static function openInvoice(int $total): Invoice
    {
        return new Invoice(1, 1, 3, 'Membership', new Money($total, 'EUR'), new Money(0, 'EUR'), InvoiceStatus::Open);
    }
}
