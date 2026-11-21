<?php

declare(strict_types=1);

namespace ClubBilling\Http\Response;

use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\Payment\Payment;
use DateTimeInterface;

/**
 * Turns domain objects into the public JSON shape. Fields are listed explicitly (never "all properties"),
 * so adding an internal field to an entity cannot leak it through the API.
 */
final class Presenters
{
    /** @return array<string, mixed> */
    public static function invoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'member_id' => $invoice->memberId,
            'description' => $invoice->description,
            'status' => $invoice->status->value,
            'currency' => $invoice->total->currency,
            'amount_total' => $invoice->total->amount,
            'amount_paid' => $invoice->paid->amount,
            'amount_outstanding' => $invoice->outstanding()->amount,
        ];
    }

    /** @return array<string, mixed> */
    public static function payment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_id' => $payment->invoiceId,
            'status' => $payment->status->value,
            'amount' => $payment->amount->amount,
            'currency' => $payment->amount->currency,
            'gateway_reference' => $payment->gatewayReference,
            'failure_reason' => $payment->failureReason,
            'created_at' => $payment->createdAt->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
