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
        $startTime = microtime(true);
        $plate = $request->getPlate();
        $customOrder = $request->getCustomOrder();

        try {
            $result = $this->useCase->execute($plate, $customOrder);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_success_total');

            \Illuminate\Support\Facades\Log::info('vehicle_debt.completed', [
                'event' => 'vehicle_debt.completed',
                'plate' => \App\Application\Support\PlateMasker::mask($plate),
                'provider' => $result->provider,
                'debts_count' => count($result->calculatedDebts->debts),
                'duration_ms' => $durationMs,
            ]);

            return response()->json($result->toArray(), 200);
        } catch (UnknownDebtTypeException $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
                'error_type' => 'unknown_debt_type',
            ]);

            \Illuminate\Support\Facades\Log::error('vehicle_debt.failed', [
                'event' => 'vehicle_debt.failed',
                'plate' => \App\Application\Support\PlateMasker::mask($plate),
                'error_type' => 'unknown_debt_type',
                'status_code' => 422,
                'reason' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'error' => 'unknown_debt_type',
                'type' => $e->getDebtType(),
            ], 422);
        } catch (AllProvidersUnavailableException $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
                'error_type' => 'all_providers_unavailable',
            ]);

            \Illuminate\Support\Facades\Log::error('vehicle_debt.failed', [
                'event' => 'vehicle_debt.failed',
                'plate' => \App\Application\Support\PlateMasker::mask($plate),
                'error_type' => 'all_providers_unavailable',
                'status_code' => 503,
                'reason' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'error' => 'all_providers_unavailable',
            ], 503);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
                'error_type' => 'unexpected_error',
            ]);

            \Illuminate\Support\Facades\Log::error('vehicle_debt.failed', [
                'event' => 'vehicle_debt.failed',
                'plate' => \App\Application\Support\PlateMasker::mask($plate),
                'error_type' => 'unexpected_error',
                'status_code' => 500,
                'reason' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'error' => 'internal_server_error',
            ], 500);
        }
    }
}

