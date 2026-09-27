<?php

use App\Http\Controllers\VehicleDebtController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'provider-rest',
    ]);
});

Route::get('/v1/vehicles/{plate}/debts', [VehicleDebtController::class, 'show']);
