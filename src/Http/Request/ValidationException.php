<?php

declare(strict_types=1);

namespace ClubBilling\Http\Request;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors field name => what is wrong with it */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }
}
