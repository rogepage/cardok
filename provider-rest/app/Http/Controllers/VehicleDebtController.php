<?php

namespace App\Http\Controllers;

use App\Services\VehicleDebtService;
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
}
