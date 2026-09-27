<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;
use App\Domain\Debt\Rounding\HalfUpRounder;

class IpvaInterestPolicy implements DebtInterestPolicyInterface
{
    /**
     * IPVA rate: 0.33% per day = 0.0033 = 33 / 10,000.
     */
    private const int RATE_NUMERATOR = 33;
    private const int RATE_DENOMINATOR = 10000;

    /**
     * IPVA cap: 20% = 0.20 = 2,000 / 10,000.
     */
    private const int CAP_NUMERATOR = 2000;

    public function supports(DebtType $type): bool
    {
        return $type === DebtType::IPVA;
    }

    public function calculateInterest(Money $originalAmount, int $daysOverdue): Money
    {
        if ($daysOverdue <= 0) {
            return Money::fromCents(0);
        }

        $cents = $originalAmount->getAmountInCents();

        // 1. Calculate unrounded interest numerator: valor_original * 0.0033 * dias_atraso
        $calculatedNumerator = $cents * self::RATE_NUMERATOR * $daysOverdue;

        // 2. Calculate unrounded cap numerator: valor_original * 0.20
        $capNumerator = $cents * self::CAP_NUMERATOR;

        // 3. Apply cap before rounding: min(juros_calculado, juros_teto)
        $appliedNumerator = min($calculatedNumerator, $capNumerator);

        // 4. Round applied interest to 2 decimal places (cents) using HALF_UP
        $roundedCents = HalfUpRounder::round($appliedNumerator, self::RATE_DENOMINATOR);

        return Money::fromCents($roundedCents);
    }
}
