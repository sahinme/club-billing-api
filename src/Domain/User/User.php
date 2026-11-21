<?php

declare(strict_types=1);

namespace ClubBilling\Domain\User;

/**
 * The authenticated caller. Deliberately has no password hash: that stays in the
 * login code path (UserCredentials) so it cannot leak through a log line or a response.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public int $clubId,
        public Role $role,
    ) {}

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->role->permissions(), true);
    }
}
