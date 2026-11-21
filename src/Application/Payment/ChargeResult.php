<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

final readonly class ChargeResult
{
    private function __construct(
        public bool $approved,
        /** The provider's id for this charge. Providers return one for declines too. */
        public string $reference,
        public ?string $declineReason,
    ) {}

    public static function approved(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function declined(string $reference, string $reason): self
    {
        return new self(false, $reference, $reason);
    }
}
