<?php

namespace App\Http\Controllers;

use App\Application\VehicleDebt\VehicleDebtService;
use App\Domain\Debt\CalculatedDebt;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Domain\Debt\Services\DebtCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleDebtIntegrationController extends Controller
{
    public function __construct(
        private readonly VehicleDebtService $vehicleDebtService,
        private readonly DebtCalculationService $calculationService,
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
            $calculatedResult = $this->calculationService->calculate($providerResponse);
        } catch (UnknownDebtTypeException $e) {
            return response()->json([
                'error' => 'unknown_debt_type',
                'type' => $e->getDebtType(),
            ], 422);
        } catch (AllProvidersUnavailableException) {
            return response()->json([
                'error' => 'all_providers_unavailable',
            ], 503);
        }

        return response()->json([
            'placa' => $calculatedResult->plate,
            'debitos' => array_map(fn (CalculatedDebt $debt) => [
                'tipo' => $debt->type->value,
                'valor_original' => $debt->originalAmount->toDecimal(),
                'valor_atualizado' => $debt->updatedAmount->toDecimal(),
                'vencimento' => $debt->dueDate->toDateString(),
                'dias_atraso' => $debt->daysOverdue,
            ], $calculatedResult->debts),
            'resumo' => [
                'total_original' => $calculatedResult->totalOriginal->toDecimal(),
                'total_atualizado' => $calculatedResult->totalUpdated->toDecimal(),
            ],
        ]);
    }
}
