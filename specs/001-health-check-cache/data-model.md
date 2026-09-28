# Data Model: Health Check Integration Cache

## Entities & Data Structures

### 1. CachedHealthCheckEnvelope
Represents the cached envelope holding the HTTP response metadata and diagnostic body.

| Field | Type | Description |
|---|---|---|
| `status_code` | `integer` | HTTP status code (`200` when all healthy, `503` if any degraded) |
| `payload` | `HealthCheckIntegrationsResponse` | The complete JSON response body |

### 2. HealthCheckIntegrationsResponse
Represents the JSON body returned by `GET /api/health/integrations`.

| Field | Type | Description |
|---|---|---|
| `status` | `string` | `"ok"` if all services responded successfully; `"degraded"` if any failed |
| `service` | `string` | Fixed string `"monolith"` |
| `services` | `array<string, ServiceDiagnosticResult>` | Map of individual dependency statuses |

### 3. HTTP Headers Contract

| Header | Direction | Value | Description |
|---|---|---|---|
| `Cache-Control` | Request | `no-cache` (optional) | When present, forces immediate live probe and cache refresh |
| `X-Cache` | Response | `HIT` or `MISS` | Indicates whether the response was served from cache or live probes |
| `X-Request-ID` | Response | UUID string | Standard distributed trace identifier |

## Lifecycle & State Transition

```mermaid
stateDiagram-v2
    [*] --> RequestReceived: GET /api/health/integrations
    RequestReceived --> CheckBypass: Inspect Cache-Control header

    CheckBypass --> ForceMiss: Header is "no-cache"
    CheckBypass --> CacheLookup: No bypass requested

    state CacheLookup {
        [*] --> CheckStore
        CheckStore --> CacheHit: Item exists in cache & TTL < 5s
        CheckStore --> CacheMiss: Item missing or TTL expired
    }

    CacheHit --> ReturnHit: Response with X-Cache: HIT (< 15ms)
    
    CacheMiss --> ProbeExternalServices: Call REST, SOAP, Payment HTTP endpoints
    ForceMiss --> ProbeExternalServices: Call REST, SOAP, Payment HTTP endpoints
    
    ProbeExternalServices --> EvaluateState: Inspect HTTP responses
    
    EvaluateState --> StoreHealthy: All healthy -> status_code 200, status "ok"
    EvaluateState --> StoreDegraded: Any failed -> status_code 503, status "degraded"
    
    StoreHealthy --> PutInCache: Cache::put (TTL = 5 seconds)
    StoreDegraded --> PutInCache: Cache::put (TTL = 5 seconds)
    
    PutInCache --> ReturnMiss: Response with X-Cache: MISS
    ReturnHit --> [*]
    ReturnMiss --> [*]
```
