<?php

declare(strict_types=1);

namespace ClubBilling\Domain;

use InvalidArgumentException;

/**
 * An amount of money in minor units (cents) plus its currency.
 *
 * Integers, never floats: 0.1 + 0.2 !== 0.3 in floating point, and a billing system cannot be
 * one cent off. Immutable: every operation returns a new Money.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function hasSameCurrencyAs(self $other): bool
    {
        return $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->hasSameCurrencyAs($other)) {
            throw new InvalidArgumentException(sprintf('Cannot combine %s with %s.', $this->currency, $other->currency));
        }
    }
}
