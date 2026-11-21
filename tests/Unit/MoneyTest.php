<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Unit;

use ClubBilling\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAddsAndSubtractsInMinorUnits(): void
    {
        $total = new Money(4999, 'EUR');

        self::assertSame(5000, $total->add(new Money(1, 'EUR'))->amount);
        self::assertSame(2999, $total->subtract(new Money(2000, 'EUR'))->amount);
    }

    public function testCannotGoNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, 'EUR')->subtract(new Money(101, 'EUR'));
    }

    public function testRefusesToMixCurrencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, 'EUR')->add(new Money(100, 'USD'));
    }

    #[TestWith(['eur'])]
    #[TestWith(['EURO'])]
    #[TestWith([''])]
    public function testRejectsInvalidCurrencyCodes(string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, $currency);
    }
}
