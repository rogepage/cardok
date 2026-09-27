<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;
use App\Domain\Debt\Rounding\HalfUpRounder;

class MultaInterestPolicy implements DebtInterestPolicyInterface
{
    /**
     * MULTA rate: 1.00% per day = 0.01 = 1 / 100.
     */
    private const int RATE_NUMERATOR = 1;
    private const int RATE_DENOMINATOR = 100;

    public function supports(DebtType $type): bool
    {
        return $type === DebtType::MULTA;
    }

    public function calculateInterest(Money $originalAmount, int $daysOverdue): Money
    {
        if ($daysOverdue <= 0) {
            return Money::fromCents(0);
        }

        $cents = $originalAmount->getAmountInCents();

        // 1. Calculate unrounded interest: valor_original * 0.01 * dias
        $calculatedNumerator = $cents * self::RATE_NUMERATOR * $daysOverdue;

        // 2. Round interest to 2 decimal places (cents) using HALF_UP
        $roundedCents = HalfUpRounder::round($calculatedNumerator, self::RATE_DENOMINATOR);

        return Money::fromCents($roundedCents);
    }
}
