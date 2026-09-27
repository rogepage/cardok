<?php

namespace App\Services;

use App\Data\MockVehicleDebtData;
use Illuminate\Http\JsonResponse;

class VehicleDebtService
{
    public function getDebtsResponse(string $plate, ?string $mode = null): JsonResponse
    {
        $mode = $mode ?? config('services.provider_mode') ?? env('PROVIDER_MODE', 'success');

        return match ($mode) {
            'error' => response()->json([
                'error' => 'Simulated external provider error',
            ], 500),

            'timeout' => $this->handleTimeout($plate),

            'invalid_response' => response()->json([
                'corrupted_data' => true,
                'message' => 'Invalid external provider response structure',
            ], 200),

            default => response()->json([
                'vehicle' => strtoupper(trim($plate)),
                'debts' => MockVehicleDebtData::findByPlate($plate),
            ], 200),
        };
    }

    private function handleTimeout(string $plate): JsonResponse
    {
        sleep(5);

        return response()->json([
            'vehicle' => strtoupper(trim($plate)),
            'debts' => MockVehicleDebtData::findByPlate($plate),
        ], 200);
    }
}
