<?php

namespace App\Http\Controllers;

use App\Services\VehicleDebtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VehicleDebtController extends Controller
{
    public function __construct(
        private readonly VehicleDebtService $vehicleDebtService
    ) {}

    public function show(string $plate): JsonResponse
    {
        return $this->vehicleDebtService->getDebtsResponse($plate);
    }

    public function setSimulationMode(Request $request): JsonResponse
    {
        $mode = (string) $request->input('mode', 'success');
        $this->vehicleDebtService->setSimulationMode($mode);

        return response()->json([
            'status' => 'ok',
            'mode' => $this->vehicleDebtService->getSimulationMode(),
        ]);
    }

    public function getSimulationMode(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'mode' => $this->vehicleDebtService->getSimulationMode(),
        ]);
    }
}
