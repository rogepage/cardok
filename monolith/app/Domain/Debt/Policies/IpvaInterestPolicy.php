<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;

class IpvaInterestPolicy implements DebtInterestPolicyInterface
{
    /**
     * IPVA rate: 0.33% per day (33 / 10000).
     */
    private const int RATE_NUMERATOR = 33;
    private const int RATE_DENOMINATOR = 10000;

    /**
     * IPVA cap: 20% of original value (20 / 100).
     */
    private const int CAP_PERCENTAGE = 20;

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

        // Simple interest: cents * 0.0033 * days
        // Half-up rounding with integer arithmetic: (numerator + denominator / 2) / denominator
        $numerator = $cents * self::RATE_NUMERATOR * $daysOverdue;
        $calculatedInterestCents = intdiv($numerator + (self::RATE_DENOMINATOR / 2), self::RATE_DENOMINATOR);

        // Cap: 20% of original value
        $capCents = intdiv($cents * self::CAP_PERCENTAGE, 100);

        $appliedInterestCents = min($calculatedInterestCents, $capCents);

        return Money::fromCents($appliedInterestCents);
    }
}
