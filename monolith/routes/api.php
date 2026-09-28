<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'monolith',
    ]);
});

Route::get('/metrics', function (\App\Infrastructure\Observability\SimpleMetricsRegistry $metricsRegistry) {
    return response()->json([
        'status' => 'ok',
        'metrics' => $metricsRegistry->getAll(),
    ]);
});

Route::get('/health/integrations', function (Request $request) {
    $cacheKey = 'health:integrations:status';
    $bypassCache = str_contains(strtolower((string) $request->header('Cache-Control', '')), 'no-cache');

    if (! $bypassCache) {
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['payload'], $cached['status_code'])) {
                return response()->json($cached['payload'], (int) $cached['status_code'])
                    ->header('X-Cache', 'HIT');
            }
        } catch (\Throwable $e) {
            // In case of cache driver failure, fallback gracefully to live probe
        }
    }

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

    $statusCode = $allHealthy ? 200 : 503;
    $payload = [
        'status' => $allHealthy ? 'ok' : 'degraded',
        'service' => 'monolith',
        'services' => $results,
    ];

    try {
        Cache::put($cacheKey, [
            'status_code' => $statusCode,
            'payload' => $payload,
        ], 5);
    } catch (\Throwable $e) {
        // Cache write failure ignored to avoid failing the health check
    }

    return response()->json($payload, $statusCode)
        ->header('X-Cache', 'MISS');
});

Route::middleware(['throttle:60,1'])->post('/v1/vehicles/debts', [\App\Http\Controllers\VehicleDebtIntegrationController::class, 'show']);
