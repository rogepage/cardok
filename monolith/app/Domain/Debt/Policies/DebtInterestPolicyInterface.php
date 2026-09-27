<?php

namespace App\Domain\Debt\Policies;

use App\Domain\Debt\DebtType;
use App\Domain\Debt\Money;

interface DebtInterestPolicyInterface
{
    public function supports(DebtType $type): bool;

    public function calculateInterest(Money $originalAmount, int $daysOverdue): Money;
}
