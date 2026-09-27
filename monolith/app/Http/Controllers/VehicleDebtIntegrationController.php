<?php

namespace App\Http\Controllers;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Exceptions\ProviderException;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleDebtIntegrationController extends Controller
{
    public function __construct(
        private readonly RestVehicleDebtProvider $restProvider,
        private readonly SoapVehicleDebtProvider $soapProvider,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $plate = strtoupper(trim((string) $request->input('placa')));

        if ($plate === '') {
            return response()->json([
                'error' => 'A placa do veiculo e obrigatoria.',
            ], 400);
        }

        $providerName = strtolower(trim((string) $request->input('provider', 'rest')));

        /** @var VehicleDebtProvider $provider */
        $provider = match ($providerName) {
            'soap' => $this->soapProvider,
            default => $this->restProvider,
        };

        $providerResponse = $provider->getDebts($plate);

        return response()->json([
            'placa' => $providerResponse->plate,
            'debitos' => array_map(fn (Debt $debt) => [
                'tipo' => $debt->type,
                'valor' => $debt->amount->toDecimal(),
                'vencimento' => $debt->dueDate->toDateString(),
            ], $providerResponse->debts),
        ]);
    }
}
