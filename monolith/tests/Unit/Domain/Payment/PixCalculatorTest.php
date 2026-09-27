<?php

namespace Tests\Unit\Domain\Payment;

use App\Domain\Debt\Money;
use App\Domain\Payment\Services\PixCalculator;
use PHPUnit\Framework\TestCase;

class PixCalculatorTest extends TestCase
{
    private PixCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PixCalculator();
    }

    public function test_pix_spec_total_example(): void
    {
        // 2355.93 * 0.95 = 2238.1335 -> HALF_UP: 2238.13
        $base = Money::fromDecimal('2355.93');
        $pix = $this->calculator->calculate($base);

        $this->assertSame('2238.13', $pix->totalWithDiscount->toDecimal());
    }

    public function test_pix_spec_ipva_example(): void
    {
        // 1800.00 * 0.95 = 1710.00
        $base = Money::fromDecimal('1800.00');
        $pix = $this->calculator->calculate($base);

        $this->assertSame('1710.00', $pix->totalWithDiscount->toDecimal());
    }

    public function test_pix_spec_multa_example(): void
    {
        // 555.93 * 0.95 = 528.1335 -> HALF_UP: 528.13
        $base = Money::fromDecimal('555.93');
        $pix = $this->calculator->calculate($base);

        $this->assertSame('528.13', $pix->totalWithDiscount->toDecimal());
    }

    public function test_pix_half_up_rounding_thresholds(): void
    {
        // 0.10 * 0.95 = 0.095 -> rounds to 0.10
        $m1 = Money::fromDecimal('0.10');
        $p1 = $this->calculator->calculate($m1);
        $this->assertSame('0.10', $p1->totalWithDiscount->toDecimal());

        // 0.30 * 0.95 = 0.285 -> rounds to 0.29
        $m2 = Money::fromDecimal('0.30');
        $p2 = $this->calculator->calculate($m2);
        $this->assertSame('0.29', $p2->totalWithDiscount->toDecimal());

        // 0.70 * 0.95 = 0.665 -> rounds to 0.67
        $m3 = Money::fromDecimal('0.70');
        $p3 = $this->calculator->calculate($m3);
        $this->assertSame('0.67', $p3->totalWithDiscount->toDecimal());
    }

    public function test_pix_zero_amount(): void
    {
        $zero = Money::fromCents(0);
        $pix = $this->calculator->calculate($zero);

        $this->assertSame('0.00', $pix->totalWithDiscount->toDecimal());
    }

    public function test_method_key(): void
    {
        $this->assertSame('pix', $this->calculator->getMethodKey());
    }
}
