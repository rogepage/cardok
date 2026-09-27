<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;

class MultaInterestPolicy implements DebtInterestPolicyInterface
{
    /**
     * MULTA rate: 1.00% per day (1 / 100).
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

        // Simple interest: cents * 0.01 * days
        // Half-up rounding with integer arithmetic: (numerator + denominator / 2) / denominator
        $numerator = $cents * self::RATE_NUMERATOR * $daysOverdue;
        $calculatedInterestCents = intdiv($numerator + (self::RATE_DENOMINATOR / 2), self::RATE_DENOMINATOR);

        return Money::fromCents($calculatedInterestCents);
    }
}
