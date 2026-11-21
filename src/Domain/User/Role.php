<?php

declare(strict_types=1);

namespace ClubBilling\Domain\User;

/**
 * Roles and what they may do. Kept in code (not a table) because there are three of them;
 * with per-club custom roles this would move to the database.
 */
enum Role: string
{
    /** Staff at the gym desk: sees and collects payment for every invoice of their club. */
    case FrontDesk = 'front_desk';

    /** Read-only staff, for example the bookkeeper. */
    case Auditor = 'auditor';

    /** A gym member: can see and pay only their own invoices. */
    case Member = 'member';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::FrontDesk, self::Member => [Permission::ViewInvoices, Permission::CollectPayments],
            self::Auditor => [Permission::ViewInvoices],
        };
    }

    public function isLimitedToOwnRecords(): bool
    {
        return $this === self::Member;
    }
}
