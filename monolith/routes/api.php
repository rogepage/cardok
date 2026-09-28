<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'monolith',
    ]);
});

Route::get('/health/integrations', function () {
    $services = [
        'provider-rest' => config('services.providers.rest_url') . '/api/health',
        'provider-soap' => config('services.providers.soap_url') . '/health',
        'payment-provider' => config('services.payment.url') . '/health',
    ];

    $results = [];
    $allHealthy = true;

    foreach ($services as $name => $url) {
        try {
            $response = Http::timeout((int) config('services.providers.timeout', 2))->get($url);
            $results[$name] = [
                'status' => $response->successful() ? 'ok' : 'error',
                'url' => $url,
                'http_code' => $response->status(),
                'data' => $response->json(),
            ];
            if (! $response->successful()) {
                $allHealthy = false;
            }
        } catch (\Throwable $e) {
            $allHealthy = false;
            $results[$name] = [
                'status' => 'error',
                'url' => $url,
                'http_code' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'status' => $allHealthy ? 'ok' : 'degraded',
        'service' => 'monolith',
        'services' => $results,
    ], $allHealthy ? 200 : 503);
});

Route::middleware(['throttle:60,1'])->post('/v1/vehicles/debts', [\App\Http\Controllers\VehicleDebtIntegrationController::class, 'show']);
