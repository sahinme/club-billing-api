<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Database;

use PDO;
use RuntimeException;

/**
 * Applies database/migrations/*.sql files in name order, each one only once.
 *
 * Deliberately tiny. In a real service I would use Doctrine Migrations or Phinx and run them
 * as a separate deploy step (a Kubernetes Job / Helm hook), not on application start.
 * Note: MariaDB commits DDL implicitly, so a failed migration can leave a half-applied file.
 */
final readonly class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $directory,
    ) {}

    /** @return list<string> the migrations applied by this run */
    public function migrate(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
            ) ENGINE = InnoDB',
        );

        $statement = $this->pdo->prepare('SELECT version FROM schema_migrations');
        $statement->execute();
        $alreadyApplied = $statement->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);

        $appliedNow = [];
        foreach ($files as $file) {
            $version = basename($file);
            if (in_array($version, $alreadyApplied, true)) {
                continue;
            }

            foreach ($this->statementsIn($file) as $statement) {
                $this->pdo->exec($statement);
            }

            $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)')
                ->execute(['version' => $version]);
            $appliedNow[] = $version;
        }

        return $appliedNow;
    }

    /** @return list<string> */
    private function statementsIn(string $file): array
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Cannot read migration %s.', basename($file)));
        }

        // Drop "-- comment" lines, then split on semicolons that end a line.
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = preg_split('/;\s*$/m', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $statements), static fn(string $s): bool => $s !== ''));
    }
}
