<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\ProviderDebtResponse;
use App\Domain\Debt\Services\DebtCalculationService;
use App\Domain\Payment\Services\PaymentSimulator;
use Illuminate\Support\Facades\Log;
use Throwable;

class VehicleDebtService
{
    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly ProviderExecutor $providerExecutor,
        private readonly ?DebtCalculationService $calculationService = null,
        private readonly ?PaymentSimulator $paymentSimulator = null,
    ) {}

    /**
     * @param array<int, string>|null $customOrder
     * @throws AllProvidersUnavailableException
     */
    public function getDebts(string $plate, ?array $customOrder = null): ProviderDebtResponse
    {
        $order = $customOrder ?? $this->providerResolver->getConfiguredOrder();
        $normalizedPlate = strtoupper(trim($plate));
        $errors = [];

        foreach ($order as $providerKey) {
            try {
                $provider = $this->providerResolver->resolve($providerKey);

                $response = $this->providerExecutor->execute($providerKey, $provider, $normalizedPlate);

                Log::info('Vehicle debts retrieved successfully', [
                    'event' => 'vehicle_debts_retrieved',
                    'provider' => $providerKey,
                    'plate' => ProviderExecutor::maskPlate($normalizedPlate),
                    'debts_count' => count($response->debts),
                ]);

                return $response;
            } catch (Throwable $e) {
                $errors[$providerKey] = $e->getMessage();

                Log::warning('Provider failed completely, attempting fallback', [
                    'event' => 'vehicle_provider_fallback',
                    'failed_provider' => $providerKey,
                    'plate' => ProviderExecutor::maskPlate($normalizedPlate),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('All configured vehicle debt providers failed', [
            'event' => 'all_vehicle_providers_failed',
            'plate' => ProviderExecutor::maskPlate($normalizedPlate),
            'tried_providers' => $order,
            'errors' => $errors,
        ]);

        throw new AllProvidersUnavailableException(
            'All vehicle debt providers failed: ' . json_encode($errors, JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Orchestrates the complete vehicle debt consultation workflow:
     * 1. Fetches canonical debts across configured providers with retry & fallback
     * 2. Calculates overdue interest and penalties using domain policies
     * 3. Simulates payment options (TOTAL, partials, PIX with 5%, credit card Price PMT 1x, 6x, 12x)
     *
     * @param array<int, string>|null $customOrder
     * @throws UnknownDebtTypeException
     * @throws AllProvidersUnavailableException
     */
    public function consultDebts(string $plate, ?array $customOrder = null): VehicleDebtConsultationResult
    {
        $providerResponse = $this->getDebts($plate, $customOrder);

        $calculationService = $this->calculationService ?? app(DebtCalculationService::class);
        $paymentSimulator = $this->paymentSimulator ?? app(PaymentSimulator::class);

        $calculatedResult = $calculationService->calculate($providerResponse);
        $paymentSimulation = $paymentSimulator->simulate($calculatedResult);

        return new VehicleDebtConsultationResult(
            calculatedDebts: $calculatedResult,
            payments: $paymentSimulation,
        );
    }
}
