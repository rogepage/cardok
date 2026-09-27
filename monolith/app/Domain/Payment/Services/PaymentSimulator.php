<?php

namespace App\Domain\Payment\Services;

use App\Domain\Debt\CalculatedVehicleDebts;
use App\Domain\Debt\Money;
use App\Domain\Payment\DTO\PaymentOption;
use App\Domain\Payment\DTO\PaymentSimulationResult;

class PaymentSimulator
{
    public function __construct(
        private readonly PixCalculator $pixCalculator,
        private readonly CreditCardCalculator $creditCardCalculator,
    ) {}

    public function simulate(CalculatedVehicleDebts $calculatedDebts): PaymentSimulationResult
    {
        if (empty($calculatedDebts->debts) || $calculatedDebts->totalUpdated->isZero()) {
            return new PaymentSimulationResult([]);
        }

        $options = [];

        // 1. TOTAL option uses total updated debt amount
        $options[] = $this->buildOption('TOTAL', $calculatedDebts->totalUpdated);

        // 2. Partial options: group by debt type in order of first appearance
        /** @var array<string, Money> $groupedAmounts */
        $groupedAmounts = [];

        foreach ($calculatedDebts->debts as $debt) {
            $typeName = $debt->type->value;
            if (! isset($groupedAmounts[$typeName])) {
                $groupedAmounts[$typeName] = $debt->updatedAmount;
            } else {
                $groupedAmounts[$typeName] = $groupedAmounts[$typeName]->add($debt->updatedAmount);
            }
        }

        foreach ($groupedAmounts as $typeName => $baseAmount) {
            $options[] = $this->buildOption("SOMENTE_{$typeName}", $baseAmount);
        }

        return new PaymentSimulationResult($options);
    }

    private function buildOption(string $type, Money $baseAmount): PaymentOption
    {
        return new PaymentOption(
            type: $type,
            baseAmount: $baseAmount,
            pix: $this->pixCalculator->calculate($baseAmount),
            creditCard: $this->creditCardCalculator->calculate($baseAmount),
        );
    }
}
