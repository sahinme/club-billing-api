<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\Money;
use ClubBilling\Domain\Payment\Payment;
use ClubBilling\Domain\Payment\PaymentStatus;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class PaymentRepository
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private PDO $pdo) {}

    public function createPending(Invoice $invoice, Money $amount, int $createdByUserId): Payment
    {
        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->pdo->prepare(
            'INSERT INTO payments (invoice_id, club_id, amount, currency, status, created_by_user_id, created_at, updated_at)
             VALUES (:invoice_id, :club_id, :amount, :currency, :status, :created_by, :created_at, :updated_at)',
        )->execute([
            'invoice_id' => $invoice->id,
            'club_id' => $invoice->clubId,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'status' => PaymentStatus::Pending->value,
            'created_by' => $createdByUserId,
            // Native prepared statements don't allow the same named placeholder twice.
            'created_at' => $createdAt->format(self::DATE_FORMAT),
            'updated_at' => $createdAt->format(self::DATE_FORMAT),
        ]);

        return new Payment(
            id: (int) $this->pdo->lastInsertId(),
            invoiceId: $invoice->id,
            clubId: $invoice->clubId,
            amount: $amount,
            createdAt: $createdAt,
        );
    }

    public function find(int $paymentId): ?Payment
    {
        $statement = $this->pdo->prepare(
            'SELECT id, invoice_id, club_id, amount, currency, status, gateway_reference, failure_reason, created_at
               FROM payments
              WHERE id = :id',
        );
        $statement->execute(['id' => $paymentId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new Payment(
            id: (int) $row['id'],
            invoiceId: (int) $row['invoice_id'],
            clubId: (int) $row['club_id'],
            amount: new Money((int) $row['amount'], (string) $row['currency']),
            createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
            status: PaymentStatus::from((string) $row['status']),
            gatewayReference: $row['gateway_reference'] === null ? null : (string) $row['gateway_reference'],
            failureReason: $row['failure_reason'] === null ? null : (string) $row['failure_reason'],
        );
    }

    public function hasPendingForInvoice(int $invoiceId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM payments WHERE invoice_id = :invoice_id AND status = :status LIMIT 1',
        );
        $statement->execute(['invoice_id' => $invoiceId, 'status' => PaymentStatus::Pending->value]);

        return $statement->fetchColumn() !== false;
    }

    public function save(Payment $payment): void
    {
        $this->pdo->prepare(
            'UPDATE payments
                SET status = :status, gateway_reference = :gateway_reference, failure_reason = :failure_reason,
                    updated_at = CURRENT_TIMESTAMP(6)
              WHERE id = :id',
        )->execute([
            'status' => $payment->status->value,
            'gateway_reference' => $payment->gatewayReference,
            'failure_reason' => $payment->failureReason,
            'id' => $payment->id,
        ]);
    }
}
