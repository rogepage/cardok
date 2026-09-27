<?php

namespace App\Http\Controllers;

use App\Application\VehicleDebt\GetVehicleDebtsUseCase;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use App\Http\Requests\VehicleDebtRequest;
use Illuminate\Http\JsonResponse;

class VehicleDebtIntegrationController extends Controller
{
    public function __construct(
        private readonly GetVehicleDebtsUseCase $useCase,
    ) {}

    public function show(VehicleDebtRequest $request): JsonResponse
    {
        $plate = $request->getPlate();
        $customOrder = $request->getCustomOrder();

        try {
            $result = $this->useCase->execute($plate, $customOrder);

            return response()->json($result->toArray(), 200);
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
    }
}

