<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use InvalidArgumentException;

class ProviderResolver
{
    /**
     * @var array<string, VehicleDebtProvider>
     */
    private array $providers;

    public function __construct(
        RestVehicleDebtProvider $restProvider,
        SoapVehicleDebtProvider $soapProvider,
    ) {
        $this->providers = [
            'rest' => $restProvider,
            'soap' => $soapProvider,
        ];
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
        $configured = config('services.providers.order', ['rest', 'soap']);

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

        return ! empty($order) ? $order : ['rest', 'soap'];
    }
}
