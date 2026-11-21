<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use DateTimeImmutable;

final readonly class IssuedToken
{
    public function __construct(
        #[\SensitiveParameter]
        public string $plainToken,
        public DateTimeImmutable $expiresAt,
    ) {}
}
