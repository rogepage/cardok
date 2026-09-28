<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Services\DebtCalculationService;
use App\Domain\Payment\Services\PaymentSimulator;

class GetVehicleDebtsUseCase
{
    public function __construct(
        private readonly VehicleDebtService $vehicleDebtService,
        private readonly DebtCalculationService $calculationService,
        private readonly PaymentSimulator $paymentSimulator,
    ) {}

    /**
     * Executes the end-to-end vehicle debt consultation workflow:
     * 1. Fetches canonical debts across configured providers with retry & fallback
     * 2. Calculates overdue interest and penalties using domain policies
     * 3. Simulates payment options (TOTAL, partials, PIX, credit card Price PMT)
     *
     * @param array<int, string>|null $customOrder
     * @throws UnknownDebtTypeException
     * @throws AllProvidersUnavailableException
     */
    public function execute(string $plate, ?array $customOrder = null): VehicleDebtConsultationResult
    {
        $providerResponse = $this->vehicleDebtService->getDebts($plate, $customOrder);
        $calculatedResult = $this->calculationService->calculate($providerResponse);
        $paymentSimulation = $this->paymentSimulator->simulate($calculatedResult);

        return new VehicleDebtConsultationResult(
            calculatedDebts: $calculatedResult,
            payments: $paymentSimulation,
            provider: $providerResponse->provider,
        );
    }
}
