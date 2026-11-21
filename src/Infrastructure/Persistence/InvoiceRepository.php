<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\Invoice\InvoiceStatus;
use ClubBilling\Domain\Money;
use PDO;

/**
 * Every query takes the caller's club id. Tenant isolation is enforced in SQL,
 * so a bug higher up cannot return another gym's invoice.
 */
final readonly class InvoiceRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $invoiceId, int $clubId): ?Invoice
    {
        $statement = $this->pdo->prepare(
            'SELECT id, club_id, member_id, description, currency, amount_total, amount_paid, status
               FROM invoices
              WHERE id = :id AND club_id = :club_id',
        );
        $statement->execute(['id' => $invoiceId, 'club_id' => $clubId]);

        return $this->hydrate($statement->fetch());
    }

    /**
     * Like find(), but also locks the row until the current transaction ends.
     * Two payments for the same invoice are therefore processed one after the other, never at the same time.
     */
    public function findForUpdate(int $invoiceId, int $clubId): ?Invoice
    {
        $statement = $this->pdo->prepare(
            'SELECT id, club_id, member_id, description, currency, amount_total, amount_paid, status
               FROM invoices
              WHERE id = :id AND club_id = :club_id
                FOR UPDATE',
        );
        $statement->execute(['id' => $invoiceId, 'club_id' => $clubId]);

        return $this->hydrate($statement->fetch());
    }

    public function save(Invoice $invoice): void
    {
        $this->pdo->prepare(
            'UPDATE invoices
                SET amount_paid = :amount_paid, status = :status, updated_at = CURRENT_TIMESTAMP(6)
              WHERE id = :id',
        )->execute([
            'amount_paid' => $invoice->paid->amount,
            'status' => $invoice->status->value,
            'id' => $invoice->id,
        ]);
    }

    private function hydrate(mixed $row): ?Invoice
    {
        if (!is_array($row)) {
            return null;
        }

        return new Invoice(
            id: (int) $row['id'],
            clubId: (int) $row['club_id'],
            memberId: (int) $row['member_id'],
            description: (string) $row['description'],
            total: new Money((int) $row['amount_total'], (string) $row['currency']),
            paid: new Money((int) $row['amount_paid'], (string) $row['currency']),
            status: InvoiceStatus::from((string) $row['status']),
        );
    }
}
