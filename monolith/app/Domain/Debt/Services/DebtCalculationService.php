<?php

namespace App\Domain\Debt\Services;

use App\Domain\Debt\CalculatedDebt;
use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Debt\Clock\ClockInterface;
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtType;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Money;
use App\Domain\Debt\Policies\DebtInterestPolicyRegistry;
use App\Domain\Debt\ProviderDebtResponse;
use Carbon\CarbonImmutable;

class DebtCalculationService
{
    public function __construct(
        private readonly DebtInterestPolicyRegistry $policyRegistry,
        private readonly ClockInterface $clock,
    ) {}

    public function calculate(ProviderDebtResponse $response, ?CarbonImmutable $referenceDate = null): CalculatedVehicleDebts
    {
        $refDate = ($referenceDate ?? $this->clock->now())->setTimezone('UTC')->startOfDay();
        $calculatedDebts = [];
        $totalOriginal = Money::fromCents(0);
        $totalUpdated = Money::fromCents(0);

        foreach ($response->debts as $rawDebt) {
            $debtType = DebtType::tryFromNormalized($rawDebt->type);

            if ($debtType === null) {
                throw new UnknownDebtTypeException($rawDebt->type);
            }

            $calculatedDebt = $this->calculateSingleDebt($rawDebt, $debtType, $refDate);
            $calculatedDebts[] = $calculatedDebt;

            $totalOriginal = $totalOriginal->add($calculatedDebt->originalAmount);
            $totalUpdated = $totalUpdated->add($calculatedDebt->updatedAmount);
        }

        return new CalculatedVehicleDebts(
            plate: $response->plate,
            debts: $calculatedDebts,
            totalOriginal: $totalOriginal,
            totalUpdated: $totalUpdated,
        );
    }

    public function calculateSingleDebt(Debt $debt, DebtType $debtType, CarbonImmutable $refDate): CalculatedDebt
    {
        $due = $debt->dueDate->setTimezone('UTC')->startOfDay();
        $diff = (int) $due->diffInDays($refDate, false);
        $daysOverdue = max(0, $diff);

        $policy = $this->policyRegistry->getPolicy($debtType);
        $interest = $policy->calculateInterest($debt->amount, $daysOverdue);
        $updatedAmount = $debt->amount->add($interest);

        return new CalculatedDebt(
            type: $debtType,
            originalAmount: $debt->amount,
            updatedAmount: $updatedAmount,
            interestAmount: $interest,
            dueDate: $debt->dueDate,
            daysOverdue: $daysOverdue,
        );
    }
}
