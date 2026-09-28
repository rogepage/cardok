<?php

namespace App\Http\Middleware;

use App\Infrastructure\Observability\SimpleMetricsRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    private const string HEADER_NAME = 'X-Request-ID';

    public function __construct(
        private readonly SimpleMetricsRegistry $metricsRegistry,
    ) {}

    /**
     * Handle an incoming request and ensure a Correlation ID is bound, logged, and propagated.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerValue = $request->header(self::HEADER_NAME);

        if (is_string($headerValue) && preg_match('/^[a-zA-Z0-9\-_]{1,64}$/', trim($headerValue))) {
            $requestId = trim($headerValue);
        } else {
            $requestId = (string) Str::uuid();
        }

        // Bind to request attribute and service container for global accessibility
        $request->attributes->set('request_id', $requestId);
        app()->instance('request_id', $requestId);

        // Inject into global logger context so all downstream logs automatically carry request_id
        Log::withContext(['request_id' => $requestId]);

        // Emit request.received log event (exclude internal background healthchecks to avoid noise)
        if (! $request->is('api/health*') && ! $request->is('up')) {
            Log::info('request.received', [
                'event' => 'request.received',
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        if (str_contains($request->path(), 'vehicles/debts')) {
            $this->metricsRegistry->increment('vehicle_debt_requests_total');
        }

        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }
}
