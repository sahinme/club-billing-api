<?php

declare(strict_types=1);

namespace ClubBilling\Domain;

use RuntimeException;

/**
 * A request that is well-formed but breaks a billing rule (for example "invoice is already paid").
 * The message is written for the API client and is safe to show; it never contains internal details.
 */
final class BusinessRuleViolation extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
