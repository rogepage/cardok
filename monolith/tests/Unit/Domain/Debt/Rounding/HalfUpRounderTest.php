<?php

namespace Tests\Unit\Domain\Debt\Rounding;

use App\Domain\Debt\Rounding\HalfUpRounder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HalfUpRounderTest extends TestCase
{
    public function test_half_up_spec_multa_example(): void
    {
        // valor_original = 300.50 (30050 cents)
        // taxa = 0.01 (1 / 100)
        // dias = 85
        // juros_calculado = 30050 * 1 * 85 / 100 = 2554250 / 100 (255.425)
        // HALF_UP -> 25543 cents (255.43)
        $roundedCents = HalfUpRounder::round(30050 * 1 * 85, 100);
        $this->assertSame(25543, $roundedCents);
    }

    public function test_exact_halfway_rounds_up(): void
    {
        // 0.5 -> 1
        $this->assertSame(1, HalfUpRounder::round(5, 10));
        $this->assertSame(1, HalfUpRounder::round(50, 100));
        $this->assertSame(1, HalfUpRounder::round(5000, 10000));

        // 255.425 (in cents = 25542.5) -> 25543
        $this->assertSame(25543, HalfUpRounder::round(255425, 10));
    }

    public function test_below_halfway_rounds_down(): void
    {
        // 0.49 -> 0
        $this->assertSame(0, HalfUpRounder::round(49, 100));
        $this->assertSame(0, HalfUpRounder::round(4999, 10000));

        // 255.424 (in cents = 25542.4) -> 25542
        $this->assertSame(25542, HalfUpRounder::round(255424, 10));
    }

    public function test_above_halfway_rounds_up(): void
    {
        // 0.51 -> 1
        $this->assertSame(1, HalfUpRounder::round(51, 100));
        $this->assertSame(1, HalfUpRounder::round(5001, 10000));

        // 255.426 (in cents = 25542.6) -> 25543
        $this->assertSame(25543, HalfUpRounder::round(255426, 10));
    }

    public function test_zero_numerator(): void
    {
        $this->assertSame(0, HalfUpRounder::round(0, 100));
        $this->assertSame(0, HalfUpRounder::round(0, 10000));
    }

    public function test_exact_integers(): void
    {
        $this->assertSame(100, HalfUpRounder::round(10000, 100));
        $this->assertSame(250, HalfUpRounder::round(2500000, 10000));
    }

    public function test_negative_half_up(): void
    {
        // -0.5 -> -1
        $this->assertSame(-1, HalfUpRounder::round(-5, 10));
        // -0.49 -> 0
        $this->assertSame(0, HalfUpRounder::round(-49, 100));
        // -0.51 -> -1
        $this->assertSame(-1, HalfUpRounder::round(-51, 100));
    }

    public function test_rejects_non_positive_denominator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Denominator must be positive');
        HalfUpRounder::round(100, 0);
    }
}
