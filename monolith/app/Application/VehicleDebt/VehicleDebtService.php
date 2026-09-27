<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\ProviderDebtResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class VehicleDebtService
{
    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly ProviderExecutor $providerExecutor,
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
}
