# Implementation Plan: Health Check Integration Cache

**Branch**: `001-health-check-cache` | **Date**: 2026-09-28 | **Spec**: [specs/001-health-check-cache/spec.md](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/spec.md)
**Input**: Feature specification from `/specs/001-health-check-cache/spec.md` with clarifications from Session 2026-09-28

## Summary

Implement a 5-second diagnostic cache for `GET /api/health/integrations` in `monolith/routes/api.php` using Laravel's native `Cache` facade (`Cache::get` / `Cache::put` or `Cache::remember`). This protects external provider services (`provider-rest`, `provider-soap`, `payment-provider`) from overload caused by frequent orchestrator probes (Kubernetes/ECS), dropping repeated probe response latency from >100ms to <15ms while ensuring cached status expires strictly after 5 seconds, supports on-demand bypass via `Cache-Control: no-cache`, exposes telemetry via `X-Cache: HIT|MISS`, and preserves zero domain leakage.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 10.x  
**Primary Dependencies**: `Illuminate\Support\Facades\Cache`, `Illuminate\Support\Facades\Http`, `Illuminate\Support\Carbon`, `Illuminate\Http\Request`  
**Storage**: Configured Laravel Cache Store (`array` in automated tests, `file` or `redis` in runtime)  
**Testing**: Pest / PHPUnit via `docker compose exec monolith php artisan test`  
**Target Platform**: Docker containerized Linux (`cardok-monolith`)  
**Project Type**: REST API Service  
**Performance Goals**: <15ms response on cache hit (NFR-001); 1 probe per 5-second window under bursts  
**Constraints**: 5s TTL strict; exact schema backwards-compatibility; zero modification of `app/Domain/`; support `Cache-Control: no-cache` bypass; inject `X-Cache: HIT|MISS`  
**Scale/Scope**: Endpoints polled by probes at 1-5 second intervals  

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| **Princípio I (Clean Architecture)** | Confined to HTTP / Route layer (`routes/api.php`). Absolutely zero modifications in `app/Domain/`. | PASS |
| **Princípio II (Defensive Contracts)** | The JSON response schema must strictly adhere to the contract: `status`, `service`, `services`. | PASS |
| **Princípio IV (Resilience & Timeouts)** | Retains existing 2-second timeout per provider; fallback directly to probes if cache store fails. | PASS |
| **Princípio VI (Observabilidade)** | `X-Request-ID` is assigned on every request; `X-Cache: HIT|MISS` header added; health logs remain active. | PASS |
| **Princípio VII (Test Discipline)** | 100% of existing tests continue to pass; dedicated feature test covers hit, expiry, bypass, and degraded states. | PASS |

## Project Structure

### Documentation (this feature)

```text
specs/001-health-check-cache/
├── spec.md              # Feature specification with clarifications
├── plan.md              # Implementation plan (this file)
├── research.md          # Technical research & decisions (Phase 0)
├── data-model.md        # Cached data structures, headers & state transition (Phase 1)
├── quickstart.md        # Verification scenarios & testing commands (Phase 1)
├── contracts/           # API Contract schemas (Phase 1)
│   └── health-check-contract.json
└── tasks.md             # Implementation tasks (Phase 2)
```

### Source Code (repository root)

```text
monolith/
├── routes/
│   └── api.php                                 # Route implementation: cache check, bypass, X-Cache header
└── tests/
    └── Feature/
        └── HealthCheckIntegrationCacheTest.php  # Automated tests for hit, expiry, bypass, degraded state
```

**Structure Decision**: Monolith Laravel route layer with dedicated feature test in `monolith/tests/Feature/`.

## Complexity Tracking

*No constitution violations.*
