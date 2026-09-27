<?php

namespace App\Domain\Debt;

use Carbon\CarbonImmutable;

readonly class CalculatedDebt
{
    public function __construct(
        public DebtType $type,
        public Money $originalAmount,
        public Money $updatedAmount,
        public Money $interestAmount,
        public CarbonImmutable $dueDate,
        public int $daysOverdue,
    ) {}
}
