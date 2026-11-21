<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Database;

use LogicException;
use PDO;
use Throwable;

final readonly class TransactionManager
{
    public function __construct(private PDO $pdo) {}

    /**
     * Runs $work inside one database transaction.
     * Commit when it returns, roll back when it throws (the exception is passed on unchanged).
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed
    {
        $this->assertNoOpenTransaction();

        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $error) {
            // If commit() itself failed the transaction may already be gone; rolling back then would
            // throw a second error and hide the real one.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function assertNoOpenTransaction(): void
    {
        if ($this->pdo->inTransaction()) {
            // MariaDB has no nested transactions. Fail loudly instead of silently committing early.
            throw new LogicException('A transaction is already open.');
        }
    }
}
