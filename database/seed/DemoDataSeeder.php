<?php

declare(strict_types=1);

namespace ClubBilling\Database\Seed;

use ClubBilling\Application\Auth\PasswordHasher;
use PDO;

/**
 * Demo and test data. Development only: lives in autoload-dev, so the production image does not contain it.
 * Every user shares one well-known demo password, documented in the README. These are not real credentials.
 */
final readonly class DemoDataSeeder
{
    public const string PASSWORD = 'demo-password';

    public const string FRONT_DESK = 'frontdesk@amsterdam.test';
    public const string AUDITOR = 'auditor@amsterdam.test';
    public const string MEMBER_ANNA = 'anna@amsterdam.test';
    public const string MEMBER_BOB = 'bob@amsterdam.test';
    public const string OTHER_CLUB_FRONT_DESK = 'frontdesk@rotterdam.test';

    public const int INVOICE_ANNA_OPEN = 1;      // 49.99 EUR, open
    public const int INVOICE_BOB_OPEN = 2;       // 25.00 EUR, open
    public const int INVOICE_ANNA_PAID = 3;      // 15.00 EUR, already paid
    public const int INVOICE_ANNA_LARGE = 4;     // 99.00 EUR, open, used for partial payments
    public const int INVOICE_OTHER_CLUB = 5;     // 39.99 EUR, open, belongs to the Rotterdam club

    private const array TABLES = ['outbox_messages', 'idempotency_keys', 'payments', 'invoices', 'access_tokens', 'users', 'clubs'];

    public function __construct(
        private PDO $pdo,
        private PasswordHasher $passwords,
    ) {}

    public function run(): void
    {
        $this->truncateAll();

        $this->insert('clubs', ['id' => 1, 'name' => 'Amsterdam Central Gym']);
        $this->insert('clubs', ['id' => 2, 'name' => 'Rotterdam Fit']);

        // Hashing is slow by design, so hash the shared demo password once.
        $hash = $this->passwords->hash(self::PASSWORD);
        $users = [
            [1, 1, self::FRONT_DESK, 'front_desk'],
            [2, 1, self::AUDITOR, 'auditor'],
            [3, 1, self::MEMBER_ANNA, 'member'],
            [4, 1, self::MEMBER_BOB, 'member'],
            [5, 2, self::OTHER_CLUB_FRONT_DESK, 'front_desk'],
            [6, 2, 'carla@rotterdam.test', 'member'],
        ];
        foreach ($users as [$id, $clubId, $email, $role]) {
            $this->insert('users', ['id' => $id, 'club_id' => $clubId, 'email' => $email, 'password_hash' => $hash, 'role' => $role]);
        }

        $invoices = [
            [self::INVOICE_ANNA_OPEN, 1, 3, 'Membership November', 4999, 0, 'open'],
            [self::INVOICE_BOB_OPEN, 1, 4, 'Personal training session', 2500, 0, 'open'],
            [self::INVOICE_ANNA_PAID, 1, 3, 'Towel service October', 1500, 1500, 'paid'],
            [self::INVOICE_ANNA_LARGE, 1, 3, 'Membership 3 months', 9900, 0, 'open'],
            [self::INVOICE_OTHER_CLUB, 2, 6, 'Membership November', 3999, 0, 'open'],
        ];
        foreach ($invoices as [$id, $clubId, $memberId, $description, $total, $paid, $status]) {
            $this->insert('invoices', [
                'id' => $id, 'club_id' => $clubId, 'member_id' => $memberId, 'description' => $description,
                'currency' => 'EUR', 'amount_total' => $total, 'amount_paid' => $paid, 'status' => $status,
            ]);
        }
    }

    private function truncateAll(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->pdo->exec("TRUNCATE TABLE {$table}"); // table names are constants above, not input
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @param array<string, int|string> $row */
    private function insert(string $table, array $row): void
    {
        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_map(static fn(string $c): string => ':' . $c, array_keys($row)));
        $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute($row);
    }
}
