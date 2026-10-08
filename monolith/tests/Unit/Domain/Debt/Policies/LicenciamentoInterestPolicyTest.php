<?php

namespace Tests\Unit\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;
use App\Domain\Debt\Policies\LicenciamentoInterestPolicy;
use Tests\TestCase;

class LicenciamentoInterestPolicyTest extends TestCase
{
    private LicenciamentoInterestPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new LicenciamentoInterestPolicy();
    }

    public function test_supports_only_licenciamento_type(): void
    {
        $this->assertTrue($this->policy->supports(DebtType::LICENCIAMENTO));
        $this->assertFalse($this->policy->supports(DebtType::IPVA));
        $this->assertFalse($this->policy->supports(DebtType::MULTA));
    }

    public function test_zero_days_overdue_yields_zero_interest(): void
    {
        $original = Money::fromDecimal('100.00');
        $interest = $this->policy->calculateInterest($original, 0);

        $this->assertSame('0.00', $interest->toDecimal());
        $this->assertSame(0, $interest->getAmountInCents());
    }

    public function test_ten_days_overdue_calculates_daily_rate(): void
    {
        // 10 days * 0.33% = 3.3% -> 100.00 * 0.033 = 3.30
        $original = Money::fromDecimal('100.00');
        $interest = $this->policy->calculateInterest($original, 10);

        $this->assertSame('3.30', $interest->toDecimal());
    }

    public function test_overdue_beyond_cap_limits_to_twenty_percent(): void
    {
        // 100 days * 0.33% = 33% -> capped at 20% = 20.00
        $original = Money::fromDecimal('100.00');
        $interest = $this->policy->calculateInterest($original, 100);

        $this->assertSame('20.00', $interest->toDecimal());
    }

    public function test_half_up_rounding_precision(): void
    {
        // 50.15 * 5 days * 0.0033 = 0.827475 -> rounded HALF_UP to 0.83
        $original = Money::fromDecimal('50.15');
        $interest = $this->policy->calculateInterest($original, 5);

        $this->assertSame('0.83', $interest->toDecimal());
    }
}
