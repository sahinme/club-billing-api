<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Config;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public Secret $password,
    ) {}
}
