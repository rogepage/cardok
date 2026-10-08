<?php

use App\Http\Controllers\VehicleDebtController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'provider-csv',
    ]);
});

Route::get('/v1/debts/{plate}', [VehicleDebtController::class, 'show']);
Route::get('/debts/{plate}', [VehicleDebtController::class, 'show']);
Route::get('/v1/vehicles/{plate}/debts', [VehicleDebtController::class, 'show']);

Route::post('/simulation/mode', [VehicleDebtController::class, 'setSimulationMode']);
Route::get('/simulation/mode', [VehicleDebtController::class, 'getSimulationMode']);
