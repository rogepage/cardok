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

    public function test_money_subtraction(): void
    {
        $m1 = Money::fromDecimal('500.00');
        $m2 = Money::fromDecimal('150.25');
        $diff = $m1->subtract($m2);

        $this->assertSame('349.75', $diff->toDecimal());
        $this->assertSame(34975, $diff->getAmountInCents());
    }

    public function test_money_sign_and_zero_checks(): void
    {
        $zero = Money::fromCents(0);
        $pos = Money::fromCents(100);
        $neg = Money::fromCents(-100);

        $this->assertTrue($zero->isZero());
        $this->assertFalse($pos->isZero());
        $this->assertFalse($neg->isZero());

        $this->assertTrue($pos->isPositive());
        $this->assertFalse($neg->isPositive());

        $this->assertTrue($neg->isNegative());
        $this->assertFalse($pos->isNegative());
    }

    public function test_avoids_binary_float_precision_traps(): void
    {
        // 0.58 in IEEE-754 float is 0.57999999999999996
        // Direct string parsing must yield exactly 58 cents
        $money = Money::fromDecimal('0.58');
        $this->assertSame(58, $money->getAmountInCents());
        $this->assertSame('0.58', $money->toDecimal());

        // Single digit decimal "0.5" -> 50 cents
        $half = Money::fromDecimal('0.5');
        $this->assertSame(50, $half->getAmountInCents());
        $this->assertSame('0.50', $half->toDecimal());
    }

    public function test_negative_monetary_formatting(): void
    {
        $neg = Money::fromDecimal('-25.50');
        $this->assertSame(-2550, $neg->getAmountInCents());
        $this->assertSame('-25.50', $neg->toDecimal());
    }
}
