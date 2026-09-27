<?php

use App\Http\Controllers\SoapDebtController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'provider-soap',
    ]);
});

Route::post('/soap', [SoapDebtController::class, 'handle']);

