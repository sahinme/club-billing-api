<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Application\Payment\IdempotencyKeyAlreadyClaimed;
use PDO;
use PDOException;

final readonly class IdempotencyRepository
{
    /** MariaDB/MySQL error number for "Duplicate entry for key". */
    private const int ER_DUP_ENTRY = 1062;

    public function __construct(private PDO $pdo) {}

    /**
     * Claims the key for this user. The unique index (user_id, idempotency_key) does the real work:
     * if a parallel request already inserted the same key, this INSERT waits for that transaction
     * and then fails, so only one request can ever own a key.
     *
     * @throws IdempotencyKeyAlreadyClaimed
     */
    public function claim(int $userId, string $key, string $requestFingerprint): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO idempotency_keys (user_id, idempotency_key, request_fingerprint)
                 VALUES (:user_id, :idempotency_key, :fingerprint)',
            )->execute(['user_id' => $userId, 'idempotency_key' => $key, 'fingerprint' => $requestFingerprint]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === self::ER_DUP_ENTRY) {
                throw new IdempotencyKeyAlreadyClaimed();
            }
            throw $error;
        }
    }

    public function attachPayment(int $userId, string $key, int $paymentId): void
    {
        $this->pdo->prepare(
            'UPDATE idempotency_keys SET payment_id = :payment_id WHERE user_id = :user_id AND idempotency_key = :idempotency_key',
        )->execute(['payment_id' => $paymentId, 'user_id' => $userId, 'idempotency_key' => $key]);
    }

    public function find(int $userId, string $key): ?IdempotencyRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT request_fingerprint, payment_id FROM idempotency_keys WHERE user_id = :user_id AND idempotency_key = :idempotency_key',
        );
        $statement->execute(['user_id' => $userId, 'idempotency_key' => $key]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new IdempotencyRecord(
            requestFingerprint: (string) $row['request_fingerprint'],
            paymentId: $row['payment_id'] === null ? null : (int) $row['payment_id'],
        );
    }
}
