<?php

namespace App\Domain\Debt;

readonly class CalculatedVehicleDebts
{
    /**
     * @param array<int, CalculatedDebt> $debts
     */
    public function __construct(
        public string $plate,
        public array $debts,
        public Money $totalOriginal,
        public Money $totalUpdated,
    ) {}
}
