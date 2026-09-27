<?php

namespace Tests\Unit\Domain\Debt;

use App\Domain\Debt\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_creates_money_from_decimal_string_and_formats_properly(): void
    {
        $money = Money::fromDecimal('1500.00');

        $this->assertSame(150000, $money->getAmountInCents());
        $this->assertSame('1500.00', $money->toDecimal());
    }

    public function test_creates_money_from_float_and_int(): void
    {
        $fromFloat = Money::fromDecimal(300.50);
        $fromInt = Money::fromDecimal(100);

        $this->assertSame(30050, $fromFloat->getAmountInCents());
        $this->assertSame('300.50', $fromFloat->toDecimal());

        $this->assertSame(10000, $fromInt->getAmountInCents());
        $this->assertSame('100.00', $fromInt->toDecimal());
    }

    public function test_rejects_invalid_decimal_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('abc');
    }

    public function test_money_equality(): void
    {
        $m1 = Money::fromDecimal('1500.00');
        $m2 = Money::fromCents(150000);
        $m3 = Money::fromDecimal('1500.01');

        $this->assertTrue($m1->equals($m2));
        $this->assertFalse($m1->equals($m3));
    }
}
