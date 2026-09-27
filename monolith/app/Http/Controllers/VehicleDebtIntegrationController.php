<?php

namespace App\Http\Controllers;

use App\Application\VehicleDebt\VehicleDebtService;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleDebtIntegrationController extends Controller
{
    public function __construct(
        private readonly VehicleDebtService $vehicleDebtService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $plate = strtoupper(trim((string) $request->input('placa')));

        if ($plate === '') {
            return response()->json([
                'error' => 'A placa do veiculo e obrigatoria.',
            ], 400);
        }

        $customOrder = null;
        if ($request->filled('provider')) {
            $customOrder = [strtolower(trim((string) $request->input('provider')))];
        }

        try {
            $providerResponse = $this->vehicleDebtService->getDebts($plate, $customOrder);
        } catch (AllProvidersUnavailableException) {
            return response()->json([
                'error' => 'all_providers_unavailable',
            ], 503);
        }

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
