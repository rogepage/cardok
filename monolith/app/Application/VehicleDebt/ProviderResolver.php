<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use InvalidArgumentException;

class ProviderResolver
{
    /**
     * @var array<string, VehicleDebtProvider>
     */
    private array $providers = [];

    /**
     * @param iterable<string, VehicleDebtProvider>|VehicleDebtProvider $providersOrRest
     * @param VehicleDebtProvider|null $soapProvider
     */
    public function __construct(
        iterable|VehicleDebtProvider $providersOrRest = [],
        ?VehicleDebtProvider $soapProvider = null,
    ) {
        if ($providersOrRest instanceof VehicleDebtProvider) {
            $this->register('rest', $providersOrRest);
            if ($soapProvider !== null) {
                $this->register('soap', $soapProvider);
            }
        } elseif (is_iterable($providersOrRest)) {
            foreach ($providersOrRest as $key => $provider) {
                if ($provider instanceof VehicleDebtProvider) {
                    $this->register((string) $key, $provider);
                }
            }
        }
    }

    public function register(string $providerKey, VehicleDebtProvider $provider): self
    {
        $this->providers[strtolower(trim($providerKey))] = $provider;

        return $this;
    }

    public function resolve(string $providerKey): VehicleDebtProvider
    {
        $normalizedKey = strtolower(trim($providerKey));

        if (! isset($this->providers[$normalizedKey])) {
            throw new InvalidArgumentException("Unknown vehicle debt provider: {$providerKey}");
        }

        return $this->providers[$normalizedKey];
    }

    /**
     * @return array<int, string>
     */
    public function getConfiguredOrder(): array
    {
        $configured = config('services.providers.order', ['rest', 'soap', 'csv']);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $order = [];
        foreach ((array) $configured as $key) {
            $normalized = strtolower(trim($key));
            if ($normalized !== '' && isset($this->providers[$normalized])) {
                $order[] = $normalized;
            }
        }

        return ! empty($order) ? $order : array_keys($this->providers);
    }
}
