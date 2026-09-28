# Quickstart: Health Check Integration Cache

## Manual Validation

### 1. Verification of Cache Miss & Hit with X-Cache Header
Run two consecutive curls inspecting headers:
```bash
# First request: Cache Miss -> X-Cache: MISS
curl -i http://localhost:8000/api/health/integrations | grep -i "x-cache"

# Second request (< 5 seconds): Cache Hit -> X-Cache: HIT in < 15ms
curl -i http://localhost:8000/api/health/integrations | grep -i "x-cache"
```

### 2. Verification of Cache Bypass via Header
Send a request with `Cache-Control: no-cache`:
```bash
# Bypasses cache immediately -> X-Cache: MISS
curl -i -H "Cache-Control: no-cache" http://localhost:8000/api/health/integrations | grep -i "x-cache"
```

### 3. Verification of Cache Expiry
Wait 6 seconds and request again:
```bash
sleep 6
curl -i http://localhost:8000/api/health/integrations | grep -i "x-cache"
# Triggers fresh probe -> X-Cache: MISS
```

## Automated Test Execution

Run the feature test suite:
```bash
docker compose exec monolith php artisan test --filter=HealthCheckIntegrationCacheTest
```
