<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use ClubBilling\Domain\User\User;

/** Only used during login. Keeps the password hash out of the User object that the rest of the app passes around. */
final readonly class UserCredentials
{
    public function __construct(
        public User $user,
        #[\SensitiveParameter]
        public string $passwordHash,
    ) {}
}
