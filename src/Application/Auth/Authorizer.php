<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use ClubBilling\Application\ResourceNotFound;
use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\User\Permission;
use ClubBilling\Domain\User\User;

/**
 * Answers "may this user do this?". Two levels:
 *   1. assertCan()               role level:   does the role have the permission at all?
 *   2. assertCanAccessInvoice()  record level: is this particular invoice theirs to touch?
 */
final readonly class Authorizer
{
    /** @throws AccessDenied */
    public function assertCan(User $actor, Permission $permission): void
    {
        if (!$actor->can($permission)) {
            throw new AccessDenied(sprintf('Your role (%s) does not allow %s.', $actor->role->value, $permission->value));
        }
    }

    /**
     * We answer "not found" rather than "forbidden" so nobody can probe which invoice ids exist.
     *
     * @throws ResourceNotFound
     */
    public function assertCanAccessInvoice(User $actor, Invoice $invoice): void
    {
        // Repositories already filter on club_id. Checking again costs nothing and protects against a future query that forgets.
        if ($invoice->clubId !== $actor->clubId) {
            throw new ResourceNotFound('Invoice');
        }

        if ($actor->role->isLimitedToOwnRecords() && $invoice->memberId !== $actor->id) {
            throw new ResourceNotFound('Invoice');
        }
    }
}
