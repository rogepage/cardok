# Research: Health Check Integration Cache

## 1. Problem Statement & Background
The integration health check route `GET /api/health/integrations` in `monolith/routes/api.php` executes synchronous network calls to three external endpoints:
1. `provider-rest` (`GET /api/health`)
2. `provider-soap` (`GET /health`)
3. `payment-provider` (`GET /health`)

When monitoring agents or container orchestrators query this route at intervals of 1 to 5 seconds, it induces network traffic across container boundaries and introduces potential latency spikes (>100ms) or false unhealthy states under network jitter.

## 2. Research Findings & Technical Decisions

### Decision 1: Cache Mechanism & Storage Driver
- **Decision**: Use Laravel's standard `Illuminate\Support\Facades\Cache` facade with integer TTL (`5` seconds) using `Cache::remember('health:integrations:status', 5, $callback)`.
- **Rationale**: 
  - Using an explicit integer `5` (seconds) guarantees maximum compatibility across cache drivers (`array`, `file`, `redis`, `database`) and avoids time zone/microsecond delta issues during calculations.
  - Requires zero new packages or changes to core infrastructure.

### Decision 2: Cache Key & Payload Serialization
- **Decision**:
  - Key: `health:integrations:status`
  - Value: Associative array capturing both the HTTP status code and the JSON payload:
    ```php
    [
        'status_code' => 200, // or 503
        'payload' => [
            'status' => 'ok', // or 'degraded'
            'service' => 'monolith',
            'services' => [
                'provider-rest' => [...],
                'provider-soap' => [...],
                'payment-provider' => [...]
            ]
        ]
    ]
    ```

### Decision 3: Cache Bypass Protocol (`Cache-Control: no-cache`)
- **Decision**: Inspect the incoming request headers via `request()->header('Cache-Control')`. If it contains `no-cache`, skip the cache lookup, perform the active probes against external services, and update the cache with the fresh result via `Cache::put('health:integrations:status', $freshResult, 5)`.
- **Rationale**: Standard HTTP semantics (`RFC 7234`). Allows SREs, monitoring alerts, or engineers to force live diagnostic probes without waiting for TTL expiration.

### Decision 4: Observability Header (`X-Cache`)
- **Decision**: Inject `X-Cache: HIT` when served from cache, and `X-Cache: MISS` when served from active probing (including cache misses, expired cache, or `Cache-Control: no-cache` bypass).
- **Rationale**: Provides instant telemetry for probes, load balancers, and developers without modifying the JSON payload contract.

### Decision 5: Resilience & Fault Tolerance (Cache Failure Fallback)
- **Decision**: Wrap the cache lookup in a `try / catch (\Throwable $e)` block. If the cache layer encounters an operational error, the endpoint logs a warning, sets `X-Cache: MISS`, and falls back immediately to direct diagnostic probe execution.
- **Rationale**: Telemetry and diagnostic endpoints must remain accessible even if caching services suffer transient failures.
