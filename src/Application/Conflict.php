<?php

declare(strict_types=1);

namespace ClubBilling\Application;

use RuntimeException;

/** The request clashes with another request or with the current state (HTTP 409). Message is client-safe. */
final class Conflict extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
