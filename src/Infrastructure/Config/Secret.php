<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Config;

/**
 * Wraps a secret value so it cannot end up in logs or debug output by accident.
 * var_dump(), print_r() and the logger only ever see "***". Code must call reveal() on purpose.
 */
final readonly class Secret
{
    public function __construct(
        #[\SensitiveParameter]
        private string $value,
    ) {}

    public function reveal(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    public function __toString(): string
    {
        return '***';
    }
}
