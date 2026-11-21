<?php

declare(strict_types=1);

namespace ClubBilling\Application;

use RuntimeException;

final class ResourceNotFound extends RuntimeException
{
    public function __construct(string $resource)
    {
        parent::__construct(sprintf('%s not found.', $resource));
    }
}
