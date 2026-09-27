<?php

namespace App\Domain\Debt\Contracts;

use App\Domain\Debt\ProviderDebtResponse;

interface VehicleDebtProvider
{
    public function getDebts(string $plate): ProviderDebtResponse;
}
