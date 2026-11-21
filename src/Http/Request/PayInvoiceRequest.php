<?php

declare(strict_types=1);

namespace ClubBilling\Http\Request;

use ClubBilling\Application\Payment\PayInvoiceCommand;
use ClubBilling\Domain\Money;
use ClubBilling\Domain\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Validates the raw HTTP input and turns it into a typed command.
 * Allow-list style: every field is checked against what we accept, unknown fields are ignored.
 * All problems are reported at once, so the client can fix them in one go.
 */
final class PayInvoiceRequest
{
    /** 1,000,000.00 in minor units. Keeps arithmetic far away from integer overflow and catches typos. */
    private const int MAX_AMOUNT = 100_000_000;

    /** @throws ValidationException */
    public static function toCommand(ServerRequestInterface $request, User $actor, int $invoiceId): PayInvoiceCommand
    {
        $body = JsonBody::decode($request);
        $errors = [];

        $idempotencyKey = $request->getHeaderLine('Idempotency-Key');
        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $idempotencyKey) !== 1) {
            $errors['Idempotency-Key'] = 'Required header: 8-64 characters, letters, digits, "-" or "_". Use a new UUID per payment.';
            $idempotencyKey = null;
        }

        $amount = $body['amount'] ?? null;
        if (!is_int($amount) || $amount <= 0 || $amount > self::MAX_AMOUNT) {
            $errors['amount'] = 'Must be a positive whole number in minor units, e.g. 4999 for 49.99.';
            $amount = null;
        }

        $currency = $body['currency'] ?? null;
        if (!is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'] = 'Must be a 3-letter ISO 4217 code, e.g. "EUR".';
            $currency = null;
        }

        $paymentMethod = $body['payment_method'] ?? null;
        if (!is_string($paymentMethod) || preg_match('/^pm_[A-Za-z0-9_]{1,60}$/', $paymentMethod) !== 1) {
            // Only tokens from the provider's card form. A raw card number must never reach our servers (PCI DSS).
            $errors['payment_method'] = 'Must be a payment method token, e.g. "pm_card_ok".';
            $paymentMethod = null;
        }

        if ($idempotencyKey === null || $amount === null || $currency === null || $paymentMethod === null) {
            throw new ValidationException($errors);
        }

        return new PayInvoiceCommand($actor, $invoiceId, new Money($amount, $currency), $paymentMethod, $idempotencyKey);
    }
}
